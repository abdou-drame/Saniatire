import { useEffect, useState, type FormEvent } from "react";
import { PatientPicker } from "@/components/clinical/patient-picker";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { FieldError, Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { useAuth } from "@/hooks/use-auth";
import { useCreatePatientReferral } from "@/hooks/use-patient-referrals";
import { usePractitioners } from "@/hooks/use-practitioners";
import { useSites } from "@/hooks/use-sites";
import { useStructuresDirectory } from "@/hooks/use-structures-directory";
import { apiErrorMessage } from "@/lib/api-error";
import type { Patient } from "@/types/api";

export interface SendReferralDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/**
 * structure_origine_id n'est jamais envoyé : forcé côté serveur depuis
 * user.structure_id. Le picker de structure destinataire (useStructuresDirectory)
 * est déjà filtré serveur (actives + hors structure appelante) — le backend
 * n'empêche pas pour autant de choisir sa propre structure, ce filtre reste
 * une commodité, jamais une règle serveur annoncée comme telle.
 * Patient via PatientPicker (usePatientSearch, recherche libre par nom —
 * n'importe quel patient de la structure, pas seulement la première page du
 * répertoire), même patron que create-complaint-dialog.tsx.
 */
export function SendReferralDialog({ open, onOpenChange }: SendReferralDialogProps) {
  const { user, hasRole, hasPermission } = useAuth();
  const createReferral = useCreatePatientReferral();

  const structuresDirectory = useStructuresDirectory(hasPermission("referrals.create"));
  const practitioners = usePractitioners();
  // sites.view n'est pas accordé au rôle médecin (cf. RolePermissionSeeder) —
  // sans ce garde, un médecin ouvrant ce dialogue déclenche un 403 inutile
  // sur GET /sites (contrat documenté dans use-sites.ts).
  const sites = useSites(hasPermission("sites.view"));

  const [patient, setPatient] = useState<Patient | null>(null);
  const [structureDestinationId, setStructureDestinationId] = useState<string>("");
  const [praticienId, setPraticienId] = useState<string>("");
  const [siteOrigineId, setSiteOrigineId] = useState<string>("");
  const [motif, setMotif] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);

  useEffect(() => {
    if (!open) return;
    setPatient(null);
    setStructureDestinationId("");
    // Médecin envoyant lui-même le référencement : praticien référent = lui,
    // par défaut seulement (reste modifiable).
    setPraticienId(hasRole("medecin") && user ? String(user.id) : "");
    setSiteOrigineId("");
    setMotif("");
    setFieldErrors({});
    setSubmitError(null);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!patient) errors.patient = "Sélectionnez un patient.";
    if (!structureDestinationId) errors.structure = "Sélectionnez une structure destinataire.";
    if (!praticienId) errors.praticien = "Sélectionnez un praticien référent.";
    if (!motif.trim()) errors.motif = "Le motif est obligatoire.";
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitError(null);
    if (!validate() || !patient) return;

    try {
      await createReferral.mutateAsync({
        structure_destination_id: Number(structureDestinationId),
        site_origine_id: siteOrigineId ? Number(siteOrigineId) : undefined,
        patient_id: patient.id,
        praticien_referent_id: Number(praticienId),
        motif: motif.trim(),
      });
      onOpenChange(false);
    } catch (error) {
      setSubmitError(apiErrorMessage(error));
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Nouveau référencement</DialogTitle>
          <DialogDescription>
            Référer ce patient vers une autre structure — un accès exceptionnel et journalisé, jamais un accès
            inter-structures normal.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {submitError && (
            <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">
              {submitError}
            </p>
          )}

          <div>
            <Label>Patient</Label>
            <PatientPicker value={patient} onChange={setPatient} />
            <FieldError>{fieldErrors.patient}</FieldError>
          </div>

          <div>
            <div className="flex items-center justify-between gap-2">
              <Label htmlFor="referral-structure">Structure destinataire</Label>
              <Badge status="warning">Partage inter-structures</Badge>
            </div>
            <Select
              id="referral-structure"
              value={structureDestinationId}
              onChange={(e) => setStructureDestinationId(e.target.value)}
            >
              <option value="">Sélectionner...</option>
              {(structuresDirectory.data ?? []).map((s) => (
                <option key={s.id} value={s.id}>
                  {(s.legal_name || s.trade_name || s.code) + (s.city ? ` — ${s.city}` : "")}
                </option>
              ))}
            </Select>
            <FieldError>{fieldErrors.structure}</FieldError>
            <p className="mt-1 text-xs text-text-subtle">
              La structure sélectionnée aura accès à un résumé minimal du patient.
            </p>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label htmlFor="referral-praticien">Praticien référent</Label>
              <Select id="referral-praticien" value={praticienId} onChange={(e) => setPraticienId(e.target.value)}>
                <option value="">Sélectionner...</option>
                {(practitioners.data ?? []).map((p) => (
                  <option key={p.id} value={p.id}>
                    Dr {p.first_name} {p.last_name}
                  </option>
                ))}
              </Select>
              <FieldError>{fieldErrors.praticien}</FieldError>
            </div>
            <div>
              <Label htmlFor="referral-site">Site d'origine (optionnel)</Label>
              <Select id="referral-site" value={siteOrigineId} onChange={(e) => setSiteOrigineId(e.target.value)}>
                <option value="">Aucun</option>
                {(sites.data ?? []).map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.name}
                  </option>
                ))}
              </Select>
            </div>
          </div>

          <div>
            <Label htmlFor="referral-motif">Motif</Label>
            <Textarea id="referral-motif" rows={4} value={motif} onChange={(e) => setMotif(e.target.value)} />
            <FieldError>{fieldErrors.motif}</FieldError>
          </div>

          <DialogFooter>
            <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={createReferral.isPending}>
              {createReferral.isPending ? "Envoi..." : "Envoyer le référencement"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
