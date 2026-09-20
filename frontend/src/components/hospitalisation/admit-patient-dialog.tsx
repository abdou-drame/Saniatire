import { CheckCircle2, LoaderCircle } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { PatientPicker } from "@/components/clinical/patient-picker";
import { SiteSelectField } from "@/components/clinical/site-select-field";
import { useAdmitPatient, useWardsWithBeds } from "@/hooks/use-hospitalizations";
import { usePractitioners } from "@/hooks/use-practitioners";
import { useSiteSelection } from "@/hooks/use-site-selection";
import { apiErrorMessage } from "@/lib/api-error";
import type { Patient } from "@/types/api";

export interface AdmitPatientDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Patient préréempli (ex. depuis la fiche patient). Si absent, un sélecteur de recherche par nom est proposé. */
  patientId?: number;
  /**
   * Site fixé par l'appelant (ex. le site de la consultation en cours) — le
   * sélecteur de site n'est alors pas affiché. Omettre cette prop pour que
   * le dialogue résolve lui-même le site : automatiquement si l'utilisateur
   * n'est rattaché qu'à un seul site, sinon via un sélecteur explicite (cas
   * de l'administrateur, qui supervise plusieurs sites par conception).
   */
  fixedSiteId?: number | null;
  attendingPhysicianId: number;
  /** Service pré-sélectionné (ex. clic sur une tuile de lit libre). */
  initialWardId?: number;
  /** Lit pré-sélectionné (ex. clic sur une tuile de lit libre) — doit appartenir à `initialWardId`. */
  initialBedId?: number;
  onAdmitted?: () => void;
}

/**
 * Formulaire d'admission. Le sélecteur de lit ne propose que les lits
 * `libre` du service choisi — un choix d'UX, pas une règle de sécurité
 * dupliquée : le backend rejetterait de toute façon un lit non libre
 * (verrouillé en transaction), avec le message exact affiché ici en cas
 * d'échec (ex. course avec une autre admission concurrente).
 */
export function AdmitPatientDialog({
  open,
  onOpenChange,
  patientId,
  fixedSiteId,
  attendingPhysicianId,
  initialWardId,
  initialBedId,
  onAdmitted,
}: AdmitPatientDialogProps) {
  const [selectedPatient, setSelectedPatient] = useState<Patient | null>(null);
  const [wardId, setWardId] = useState(initialWardId ? String(initialWardId) : "");
  const [bedId, setBedId] = useState(initialBedId ? String(initialBedId) : "");
  const [physicianId, setPhysicianId] = useState("");
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [admittedId, setAdmittedId] = useState<number | null>(null);

  const wardsQuery = useWardsWithBeds();
  const practitionersQuery = usePractitioners();
  const admitPatient = useAdmitPatient();
  const siteSelection = useSiteSelection();
  const siteId = fixedSiteId !== undefined ? fixedSiteId : siteSelection.siteId;
  const showSiteSelector = fixedSiteId === undefined && siteSelection.needsManualSelection;

  useEffect(() => {
    if (!open) {
      setSelectedPatient(null);
      setWardId(initialWardId ? String(initialWardId) : "");
      setBedId(initialBedId ? String(initialBedId) : "");
      setPhysicianId("");
      setReason("");
      setError(null);
      setAdmittedId(null);
    }
  }, [open, initialWardId, initialBedId]);

  // Préremplit le médecin responsable avec l'utilisateur courant dès que la
  // liste des praticiens est chargée et qu'il en fait partie — sans forcer
  // un ID qui ne correspondrait à aucune option du sélecteur (ex. un
  // administrateur ou un membre de la direction qui admet pour le compte
  // d'un médecin doit choisir explicitement).
  useEffect(() => {
    if (!open || physicianId) return;
    if (practitionersQuery.data?.some((p) => p.id === attendingPhysicianId)) {
      setPhysicianId(String(attendingPhysicianId));
    }
  }, [open, physicianId, practitionersQuery.data, attendingPhysicianId]);

  const resolvedPatientId = patientId ?? selectedPatient?.id ?? null;

  const wards = wardsQuery.data ?? [];
  const selectedWard = wards.find((w) => String(w.id) === wardId);
  const freeBeds = useMemo(
    () => (selectedWard?.beds ?? []).filter((bed) => bed.status === "libre"),
    [selectedWard],
  );

  function handleSubmit() {
    if (!siteId || !resolvedPatientId || !bedId || !physicianId.trim() || !reason.trim()) return;
    setError(null);
    admitPatient.mutate(
      {
        site_id: siteId,
        patient_id: resolvedPatientId,
        bed_id: Number(bedId),
        attending_physician_id: Number(physicianId),
        admission_reason: reason.trim(),
      },
      {
        onSuccess: (hospitalization) => {
          setAdmittedId(hospitalization.id);
          onAdmitted?.();
        },
        onError: (err) => setError(apiErrorMessage(err)),
      },
    );
  }

  const canSubmit =
    Boolean(siteId) && Boolean(resolvedPatientId) && Boolean(bedId) && physicianId.trim() !== "" && reason.trim() !== "";

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Admettre un patient</DialogTitle>
          <DialogDescription>Sélectionnez un service, un lit libre et le médecin responsable.</DialogDescription>
        </DialogHeader>

        {admittedId ? (
          <div className="space-y-4 py-4 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success/10 text-success">
              <CheckCircle2 size={24} />
            </div>
            <p className="text-sm text-text">Hospitalisation créée — #{admittedId}</p>
          </div>
        ) : (
          <div className="space-y-4">
            {showSiteSelector ? (
              <SiteSelectField
                siteId={siteSelection.siteId}
                onChange={siteSelection.setSiteId}
                options={siteSelection.options}
                isLoading={siteSelection.isLoading}
              />
            ) : (
              !siteId && (
                <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-warning">
                  Aucun site associé — impossible d'admettre ce patient.
                </p>
              )
            )}

            {patientId === undefined && (
              <div>
                <Label>Patient</Label>
                <PatientPicker value={selectedPatient} onChange={setSelectedPatient} />
              </div>
            )}

            <div>
              <Label>Service</Label>
              <Select
                value={wardId}
                onChange={(e) => {
                  setWardId(e.target.value);
                  setBedId("");
                }}
                disabled={wardsQuery.isLoading}
              >
                <option value="">Sélectionner...</option>
                {wards.map((ward) => (
                  <option key={ward.id} value={ward.id}>
                    {ward.name}
                  </option>
                ))}
              </Select>
            </div>

            <div>
              <Label>Lit (libres uniquement)</Label>
              <Select value={bedId} onChange={(e) => setBedId(e.target.value)} disabled={!wardId}>
                <option value="">Sélectionner...</option>
                {freeBeds.map((bed) => (
                  <option key={bed.id} value={bed.id}>
                    Chambre {bed.room_number} — {bed.bed_label}
                  </option>
                ))}
              </Select>
              {wardId && freeBeds.length === 0 && (
                <p className="mt-1 text-xs text-text-subtle">Aucun lit libre dans ce service.</p>
              )}
            </div>

            <div>
              <Label>Médecin responsable</Label>
              <Select
                value={physicianId}
                onChange={(e) => setPhysicianId(e.target.value)}
                disabled={practitionersQuery.isLoading}
              >
                <option value="">Sélectionner...</option>
                {(practitionersQuery.data ?? []).map((practitioner) => (
                  <option key={practitioner.id} value={practitioner.id}>
                    {practitioner.first_name} {practitioner.last_name}
                  </option>
                ))}
              </Select>
            </div>

            <div>
              <Label>Motif d'admission</Label>
              <textarea
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                rows={3}
                className="w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                placeholder="Motif clinique de l'hospitalisation..."
              />
            </div>

            {error && (
              <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
                {error}
              </p>
            )}
          </div>
        )}

        <DialogFooter>
          {admittedId ? (
            <Button onClick={() => onOpenChange(false)}>Fermer</Button>
          ) : (
            <>
              <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={admitPatient.isPending}>
                Annuler
              </Button>
              <Button onClick={handleSubmit} disabled={!canSubmit || admitPatient.isPending}>
                {admitPatient.isPending && <LoaderCircle size={16} className="animate-spin" />}
                Admettre
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
