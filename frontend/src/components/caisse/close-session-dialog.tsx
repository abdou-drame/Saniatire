import { CheckCircle2, LoaderCircle } from "lucide-react";
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
import { Label } from "@/components/ui/label";
import { useCloseCashSession } from "@/hooks/use-cash-sessions";
import { apiErrorMessage } from "@/lib/api-error";
import type { CashSession } from "@/types/api";

export interface CloseSessionDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  session: CashSession;
}

/**
 * "Clôturer la session de caisse" — envoie uniquement montant_cloture. L'écart
 * (ecart = montant_cloture - (montant_ouverture + Σ espèces encaissées)) est
 * calculé exclusivement côté serveur : ce composant n'effectue AUCUN calcul
 * financier, il affiche tel quel le montant_cloture/ecart renvoyés par
 * l'API après clôture. Le signe de ecart n'est lu que pour choisir une
 * couleur d'affichage, jamais recalculé.
 */
export function CloseSessionDialog({ open, onOpenChange, session }: CloseSessionDialogProps) {
  const [montantCloture, setMontantCloture] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [closed, setClosed] = useState<CashSession | null>(null);

  const closeSession = useCloseCashSession();

  useEffect(() => {
    if (!open) {
      setMontantCloture("");
      setError(null);
      setClosed(null);
    }
  }, [open]);

  const canSubmit = montantCloture.trim() !== "" && Number(montantCloture) >= 0;

  function handleSubmit() {
    if (montantCloture.trim() === "") return;
    setError(null);
    closeSession.mutate(
      { id: session.id, montant_cloture: Number(montantCloture) },
      {
        onSuccess: (updated) => setClosed(updated),
        onError: (err) => setError(apiErrorMessage(err)),
      },
    );
  }

  const ecart = closed?.ecart ?? null;
  const ecartTone = ecart === null ? "neutral" : ecart > 0 ? "positive" : ecart < 0 ? "negative" : "neutral";
  const ecartLabel = ecart === null ? "—" : ecart > 0 ? "Excédent" : ecart < 0 ? "Manquant" : "Aucun écart";

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Clôturer la session de caisse</DialogTitle>
          <DialogDescription>
            Saisissez le montant compté en caisse. L'écart est calculé par le serveur.
          </DialogDescription>
        </DialogHeader>

        {closed ? (
          <div className="space-y-4 py-2">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success/10 text-success">
              <CheckCircle2 size={24} />
            </div>
            <div className="space-y-2 text-center">
              <p className="text-sm text-text">Session #{closed.id} clôturée.</p>
              <p className="text-xs text-text-muted">
                Montant de clôture déclaré :{" "}
                <span className="font-tabular text-text">
                  {closed.montant_cloture !== null ? Number(closed.montant_cloture).toLocaleString("fr-FR") : "—"}
                </span>
              </p>
              <div
                className={
                  ecartTone === "positive"
                    ? "rounded-md border border-success/30 bg-success/10 px-3 py-2 text-sm text-success"
                    : ecartTone === "negative"
                      ? "rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-sm text-danger"
                      : "rounded-md border border-border-strong bg-surface-hover px-3 py-2 text-sm text-text-muted"
                }
              >
                {ecartLabel}
                {ecart !== null && (
                  <span className="ml-1 font-tabular">({Number(ecart).toLocaleString("fr-FR")})</span>
                )}
              </div>
            </div>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="rounded-md border border-border bg-surface-hover/50 px-3 py-2 text-xs text-text-muted">
              Montant d'ouverture :{" "}
              <span className="font-tabular text-text">
                {Number(session.montant_ouverture).toLocaleString("fr-FR")}
              </span>
            </div>
            <div>
              <Label>Montant compté à la clôture</Label>
              <input
                type="number"
                min={0}
                step="0.01"
                value={montantCloture}
                onChange={(e) => setMontantCloture(e.target.value)}
                className="h-9 w-full rounded-md border border-border bg-bg px-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
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
          {closed ? (
            <Button onClick={() => onOpenChange(false)}>Fermer</Button>
          ) : (
            <>
              <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={closeSession.isPending}>
                Annuler
              </Button>
              <Button onClick={handleSubmit} disabled={!canSubmit || closeSession.isPending}>
                {closeSession.isPending && <LoaderCircle size={16} className="animate-spin" />}
                Clôturer
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
