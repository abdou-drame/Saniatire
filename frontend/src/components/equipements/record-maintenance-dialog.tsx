import { LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { useCreateMaintenance } from "@/hooks/use-biomedical-equipment";
import { apiErrorMessage } from "@/lib/api-error";
import { MAINTENANCE_TYPE_LABEL } from "@/pages/equipements/equipements-status";
import type { MaintenanceType } from "@/types/api";

export interface RecordMaintenanceDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  biomedicalEquipmentId: number;
  equipmentName?: string;
  onCreated?: () => void;
}

const MAINTENANCE_TYPES: MaintenanceType[] = ["preventive", "corrective"];

/**
 * "Enregistrer une maintenance" dialog. Submits exactly what is entered here — the
 * backend is the sole authority on validity; any 422/403 is surfaced verbatim via apiErrorMessage.
 */
export function RecordMaintenanceDialog({
  open,
  onOpenChange,
  biomedicalEquipmentId,
  equipmentName,
  onCreated,
}: RecordMaintenanceDialogProps) {
  const [type, setType] = useState<MaintenanceType>("preventive");
  const [datePrevue, setDatePrevue] = useState("");
  const [dateRealisee, setDateRealisee] = useState("");
  const [intervenantExterne, setIntervenantExterne] = useState("");
  const [cout, setCout] = useState("");
  const [description, setDescription] = useState("");
  const [error, setError] = useState<string | null>(null);

  const createMaintenance = useCreateMaintenance();

  useEffect(() => {
    if (!open) {
      setType("preventive");
      setDatePrevue("");
      setDateRealisee("");
      setIntervenantExterne("");
      setCout("");
      setDescription("");
      setError(null);
    }
  }, [open]);

  const canSubmit = Boolean(datePrevue);

  function handleSubmit() {
    if (!canSubmit) return;
    setError(null);
    createMaintenance.mutate(
      {
        biomedical_equipment_id: biomedicalEquipmentId,
        type,
        date_prevue: datePrevue,
        date_realisee: dateRealisee || undefined,
        intervenant_externe: intervenantExterne.trim() || undefined,
        cout: cout.trim() ? Number(cout) : undefined,
        description: description.trim() || undefined,
      },
      {
        onSuccess: () => {
          onCreated?.();
          onOpenChange(false);
        },
        onError: (err) => setError(apiErrorMessage(err)),
      },
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Enregistrer une maintenance</DialogTitle>
          <DialogDescription>
            {equipmentName ? `Pour l'équipement « ${equipmentName} ».` : "Nouvelle intervention de maintenance."}
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Type</Label>
              <Select value={type} onChange={(e) => setType(e.target.value as MaintenanceType)}>
                {MAINTENANCE_TYPES.map((t) => (
                  <option key={t} value={t}>
                    {MAINTENANCE_TYPE_LABEL[t]}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <Label>Coût (optionnel)</Label>
              <Input
                type="number"
                min={0}
                step="0.01"
                value={cout}
                onChange={(e) => setCout(e.target.value)}
                placeholder="ex. 15000"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Date prévue</Label>
              <Input type="date" value={datePrevue} onChange={(e) => setDatePrevue(e.target.value)} />
            </div>
            <div>
              <Label>Date réalisée (optionnel)</Label>
              <Input type="date" value={dateRealisee} onChange={(e) => setDateRealisee(e.target.value)} />
            </div>
          </div>

          <div>
            <Label>Intervenant</Label>
            <Input
              value={intervenantExterne}
              onChange={(e) => setIntervenantExterne(e.target.value)}
              placeholder="ex. Société XYZ Maintenance"
            />
          </div>

          <div className="space-y-1.5">
            <Label>Description</Label>
            <textarea
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              rows={3}
              className="w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              placeholder="Détails de l'intervention..."
            />
          </div>

          {error && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>
          )}
        </div>

        <DialogFooter>
          <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={createMaintenance.isPending}>
            Annuler
          </Button>
          <Button onClick={handleSubmit} disabled={!canSubmit || createMaintenance.isPending}>
            {createMaintenance.isPending && <LoaderCircle size={16} className="animate-spin" />}
            Enregistrer
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
