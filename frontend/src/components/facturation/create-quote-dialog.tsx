import { CheckCircle2, LoaderCircle, Plus, Trash2 } from "lucide-react";
import { useEffect, useState } from "react";
import { PatientPicker } from "@/components/clinical/patient-picker";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { useCreateQuote } from "@/hooks/use-quotes";
import { useSites } from "@/hooks/use-sites";
import { apiErrorMessage } from "@/lib/api-error";
import type { Patient } from "@/types/api";

export interface CreateQuoteDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

const CATEGORIE_OPTIONS = ["consultation", "laboratoire", "imagerie", "autre"];

interface DraftLine {
  key: number;
  libelle: string;
  categorie: string;
  quantite: string;
  prixUnitaire: string;
}

let nextLineKey = 1;
function emptyLine(): DraftLine {
  return { key: nextLineKey++, libelle: "", categorie: CATEGORIE_OPTIONS[0], quantite: "1", prixUnitaire: "" };
}

/**
 * "Nouveau devis" — construit librement une liste de lignes (libellé /
 * catégorie / quantité / prix unitaire). Le total affiché ici est un
 * simple aperçu de brouillon Σ(quantite × prix_unitaire) — la même formule
 * que le backend applique dans store(), sans jamais tenter de répartition
 * assurance (celle-ci n'existe qu'après conversion en facture, calculée
 * côté serveur). Le backend reste seul décideur du montant_total final.
 */
export function CreateQuoteDialog({ open, onOpenChange }: CreateQuoteDialogProps) {
  const [patient, setPatient] = useState<Patient | null>(null);
  const [siteId, setSiteId] = useState<number | null>(null);
  const [lines, setLines] = useState<DraftLine[]>([emptyLine()]);
  const [error, setError] = useState<string | null>(null);
  const [created, setCreated] = useState<{ id: number; numero: string } | null>(null);

  const sitesQuery = useSites();
  const createQuote = useCreateQuote();

  useEffect(() => {
    if (!open) {
      setPatient(null);
      setSiteId(null);
      setLines([emptyLine()]);
      setError(null);
      setCreated(null);
    }
  }, [open]);

  function updateLine(key: number, patch: Partial<DraftLine>) {
    setLines((prev) => prev.map((line) => (line.key === key ? { ...line, ...patch } : line)));
  }

  function addLine() {
    setLines((prev) => [...prev, emptyLine()]);
  }

  function removeLine(key: number) {
    setLines((prev) => (prev.length > 1 ? prev.filter((line) => line.key !== key) : prev));
  }

  const validLines = lines.filter(
    (line) =>
      line.libelle.trim() !== "" &&
      line.categorie.trim() !== "" &&
      line.quantite.trim() !== "" &&
      Number(line.quantite) > 0 &&
      line.prixUnitaire.trim() !== "" &&
      Number(line.prixUnitaire) >= 0,
  );

  const draftTotal = validLines.reduce((sum, line) => sum + Number(line.quantite) * Number(line.prixUnitaire), 0);

  const canSubmit = Boolean(patient) && Boolean(siteId) && validLines.length > 0;

  function handleSubmit() {
    if (!patient || !siteId || validLines.length === 0) return;
    setError(null);
    createQuote.mutate(
      {
        patient_id: patient.id,
        site_id: siteId,
        items: validLines.map((line) => ({
          libelle: line.libelle.trim(),
          categorie: line.categorie.trim(),
          quantite: Number(line.quantite),
          prix_unitaire: Number(line.prixUnitaire),
        })),
      },
      {
        onSuccess: (quote) => setCreated({ id: quote.id, numero: quote.numero }),
        onError: (err) => setError(apiErrorMessage(err)),
      },
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>Nouveau devis</DialogTitle>
          <DialogDescription>Composez le devis avec une ou plusieurs lignes.</DialogDescription>
        </DialogHeader>

        {created ? (
          <div className="space-y-4 py-4 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success/10 text-success">
              <CheckCircle2 size={24} />
            </div>
            <p className="text-sm text-text">
              Devis émis — {created.numero} (#{created.id})
            </p>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <Label>Patient</Label>
                <PatientPicker value={patient} onChange={setPatient} />
              </div>
              <div>
                <Label>Site</Label>
                <Select
                  value={siteId ?? ""}
                  onChange={(e) => setSiteId(e.target.value ? Number(e.target.value) : null)}
                  disabled={sitesQuery.isLoading}
                >
                  <option value="">Sélectionner un site</option>
                  {(sitesQuery.data ?? []).map((site) => (
                    <option key={site.id} value={site.id}>
                      {site.name}
                    </option>
                  ))}
                </Select>
              </div>
            </div>

            <div className="space-y-2">
              <div className="flex items-center justify-between">
                <Label className="mb-0">Lignes du devis</Label>
                <Button type="button" variant="secondary" size="sm" onClick={addLine}>
                  <Plus size={14} />
                  Ajouter une ligne
                </Button>
              </div>

              <div className="space-y-2">
                {lines.map((line) => (
                  <div key={line.key} className="grid grid-cols-12 items-end gap-2 rounded-md border border-border p-2">
                    <div className="col-span-4">
                      <Label className="text-[11px]">Libellé</Label>
                      <input
                        type="text"
                        value={line.libelle}
                        onChange={(e) => updateLine(line.key, { libelle: e.target.value })}
                        className="h-9 w-full rounded-md border border-border bg-bg px-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                      />
                    </div>
                    <div className="col-span-3">
                      <Label className="text-[11px]">Catégorie</Label>
                      <Select
                        value={line.categorie}
                        onChange={(e) => updateLine(line.key, { categorie: e.target.value })}
                      >
                        {CATEGORIE_OPTIONS.map((cat) => (
                          <option key={cat} value={cat}>
                            {cat}
                          </option>
                        ))}
                      </Select>
                    </div>
                    <div className="col-span-2">
                      <Label className="text-[11px]">Quantité</Label>
                      <input
                        type="number"
                        min={1}
                        value={line.quantite}
                        onChange={(e) => updateLine(line.key, { quantite: e.target.value })}
                        className="h-9 w-full rounded-md border border-border bg-bg px-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                      />
                    </div>
                    <div className="col-span-2">
                      <Label className="text-[11px]">Prix unitaire</Label>
                      <input
                        type="number"
                        min={0}
                        step="0.01"
                        value={line.prixUnitaire}
                        onChange={(e) => updateLine(line.key, { prixUnitaire: e.target.value })}
                        className="h-9 w-full rounded-md border border-border bg-bg px-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                      />
                    </div>
                    <div className="col-span-1 flex justify-end">
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => removeLine(line.key)}
                        disabled={lines.length === 1}
                        aria-label="Retirer la ligne"
                      >
                        <Trash2 size={14} />
                      </Button>
                    </div>
                  </div>
                ))}
              </div>

              <p className="rounded-md border border-border-strong/50 bg-surface-hover/50 px-3 py-2 text-xs text-text-muted">
                Total du devis (répartition assurance calculée uniquement à la conversion en facture) :{" "}
                <span className="font-tabular font-medium text-text">{draftTotal.toLocaleString("fr-FR")}</span>
              </p>
            </div>

            {error && (
              <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
                {error}
              </p>
            )}
          </div>
        )}

        <DialogFooter>
          {created ? (
            <Button onClick={() => onOpenChange(false)}>Fermer</Button>
          ) : (
            <>
              <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={createQuote.isPending}>
                Annuler
              </Button>
              <Button onClick={handleSubmit} disabled={!canSubmit || createQuote.isPending}>
                {createQuote.isPending && <LoaderCircle size={16} className="animate-spin" />}
                Créer le devis
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
