import { BedDouble, Plus } from "lucide-react";
import { useEffect, useState, type FormEvent } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { FieldError, Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/loading-state";
import { useCreateBed, useUpdateBed, useWard } from "@/hooks/use-wards";
import { apiErrorMessage } from "@/lib/api-error";
import { BED_STATUS_BADGE, BED_STATUS_LABEL } from "@/pages/hospitalisation/hospitalisation-status";
import type { Bed, BedStatus, Ward } from "@/types/api";

const BED_STATUS_VALUES: BedStatus[] = ["libre", "occupe", "reserve", "entretien", "indisponible"];

export interface WardBedsDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  ward: Ward | null;
}

function BedRow({ bed }: { bed: Bed }) {
  const updateBed = useUpdateBed();
  const [error, setError] = useState<string | null>(null);

  async function handleStatusChange(status: BedStatus) {
    setError(null);
    try {
      await updateBed.mutateAsync({ id: bed.id, status });
    } catch (err) {
      setError(apiErrorMessage(err));
    }
  }

  return (
    <div className="flex flex-col gap-1 rounded-md border border-border p-3">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-text">
            Chambre {bed.room_number} — Lit {bed.bed_label}
          </p>
          <Badge status={BED_STATUS_BADGE[bed.status]}>{BED_STATUS_LABEL[bed.status]}</Badge>
        </div>
        <Select
          value={bed.status}
          onChange={(e) => handleStatusChange(e.target.value as BedStatus)}
          disabled={updateBed.isPending}
          className="w-40"
        >
          {BED_STATUS_VALUES.map((status) => (
            <option key={status} value={status}>
              {BED_STATUS_LABEL[status]}
            </option>
          ))}
        </Select>
      </div>
      {error && <p className="text-xs text-danger">{error}</p>}
    </div>
  );
}

function NewBedForm({ ward }: { ward: Ward }) {
  const createBed = useCreateBed();
  const [roomNumber, setRoomNumber] = useState("");
  const [bedLabel, setBedLabel] = useState("");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);

  function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!roomNumber.trim()) errors.room_number = "Numéro de chambre obligatoire.";
    if (!bedLabel.trim()) errors.bed_label = "Nom/numéro du lit obligatoire.";
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitError(null);
    if (!validate()) return;

    try {
      await createBed.mutateAsync({
        site_id: ward.site_id,
        ward_id: ward.id,
        room_number: roomNumber.trim(),
        bed_label: bedLabel.trim(),
      });
      setRoomNumber("");
      setBedLabel("");
    } catch (error) {
      setSubmitError(apiErrorMessage(error));
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-3 rounded-md border border-dashed border-border p-3">
      {submitError && <p className="text-xs text-danger">{submitError}</p>}
      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label htmlFor="bed-room-number">Chambre</Label>
          <Input id="bed-room-number" value={roomNumber} onChange={(e) => setRoomNumber(e.target.value)} placeholder="Ex. 204" />
          <FieldError>{fieldErrors.room_number}</FieldError>
        </div>
        <div>
          <Label htmlFor="bed-label">Lit</Label>
          <Input id="bed-label" value={bedLabel} onChange={(e) => setBedLabel(e.target.value)} placeholder="Ex. A" />
          <FieldError>{fieldErrors.bed_label}</FieldError>
        </div>
      </div>
      <Button type="submit" size="sm" disabled={createBed.isPending}>
        <Plus size={14} />
        {createBed.isPending ? "Ajout..." : "Ajouter le lit"}
      </Button>
    </form>
  );
}

export function WardBedsDialog({ open, onOpenChange, ward }: WardBedsDialogProps) {
  const wardQuery = useWard(ward?.id);
  const [showNewBedForm, setShowNewBedForm] = useState(false);

  useEffect(() => {
    if (!open) setShowNewBedForm(false);
  }, [open]);

  const beds = wardQuery.data?.beds ?? [];

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>Lits — {ward?.name}</DialogTitle>
          <DialogDescription>Statut mis à jour immédiatement côté serveur.</DialogDescription>
        </DialogHeader>

        <div className="space-y-3">
          {wardQuery.isError ? (
            <ErrorState message={apiErrorMessage(wardQuery.error)} onRetry={() => wardQuery.refetch()} />
          ) : wardQuery.isLoading ? (
            <Skeleton className="h-32 w-full" />
          ) : beds.length === 0 ? (
            <EmptyState icon={BedDouble} title="Aucun lit" description="Ajoutez le premier lit de ce service." />
          ) : (
            <div className="max-h-80 space-y-2 overflow-y-auto">
              {beds.map((bed) => (
                <BedRow key={bed.id} bed={bed} />
              ))}
            </div>
          )}

          {ward && !showNewBedForm && (
            <Button variant="secondary" size="sm" onClick={() => setShowNewBedForm(true)}>
              <Plus size={14} />
              Nouveau lit
            </Button>
          )}
          {ward && showNewBedForm && <NewBedForm ward={ward} />}
        </div>

        <DialogFooter>
          <Button variant="secondary" onClick={() => onOpenChange(false)}>
            Fermer
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
