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
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { useAdmitPatient, useWardsWithBeds } from "@/hooks/use-hospitalizations";
import { apiErrorMessage } from "@/lib/api-error";

export interface AdmitPatientDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Patient préréempli (ex. depuis la fiche patient). Si absent, un champ ID patient éditable est proposé. */
  patientId?: number;
  siteId: number | null;
  attendingPhysicianId: number;
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
  siteId,
  attendingPhysicianId,
  onAdmitted,
}: AdmitPatientDialogProps) {
  const [patientIdInput, setPatientIdInput] = useState(patientId ? String(patientId) : "");
  const [wardId, setWardId] = useState("");
  const [bedId, setBedId] = useState("");
  const [physicianId, setPhysicianId] = useState(String(attendingPhysicianId));
  const [reason, setReason] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [admittedId, setAdmittedId] = useState<number | null>(null);

  const wardsQuery = useWardsWithBeds();
  const admitPatient = useAdmitPatient();

  useEffect(() => {
    if (!open) {
      setPatientIdInput(patientId ? String(patientId) : "");
      setWardId("");
      setBedId("");
      setPhysicianId(String(attendingPhysicianId));
      setReason("");
      setError(null);
      setAdmittedId(null);
    }
  }, [open, patientId, attendingPhysicianId]);

  const resolvedPatientId = patientId ?? (patientIdInput.trim() ? Number(patientIdInput) : null);

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
            {!siteId && (
              <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-warning">
                Aucun site associé — impossible d'admettre ce patient.
              </p>
            )}

            {patientId === undefined && (
              <div>
                <Label>ID patient</Label>
                <Input
                  type="number"
                  min={1}
                  value={patientIdInput}
                  onChange={(e) => setPatientIdInput(e.target.value)}
                  placeholder="ex. 42"
                />
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
              <Label>ID médecin responsable</Label>
              <Input
                type="number"
                min={1}
                value={physicianId}
                onChange={(e) => setPhysicianId(e.target.value)}
              />
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
