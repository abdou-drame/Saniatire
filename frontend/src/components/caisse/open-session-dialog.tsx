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
import { Select } from "@/components/ui/select";
import { useOpenCashSession } from "@/hooks/use-cash-sessions";
import { useSites } from "@/hooks/use-sites";
import { apiErrorMessage } from "@/lib/api-error";

export interface OpenSessionDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/**
 * "Ouvrir une session de caisse" — envoie site_id + montant_ouverture ;
 * caissier_id est déduit par le backend depuis le jeton d'authentification,
 * on ne l'envoie jamais. Si une session est déjà ouverte pour ce caissier sur
 * ce site, le backend refuse (422) avec un message verbatim, affiché tel quel.
 */
export function OpenSessionDialog({ open, onOpenChange }: OpenSessionDialogProps) {
  const [siteId, setSiteId] = useState<number | null>(null);
  const [montantOuverture, setMontantOuverture] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [createdId, setCreatedId] = useState<number | null>(null);

  const sitesQuery = useSites();
  const openSession = useOpenCashSession();

  useEffect(() => {
    if (!open) {
      setSiteId(null);
      setMontantOuverture("");
      setError(null);
      setCreatedId(null);
    }
  }, [open]);

  const canSubmit = Boolean(siteId) && montantOuverture.trim() !== "" && Number(montantOuverture) >= 0;

  function handleSubmit() {
    if (!siteId || montantOuverture.trim() === "") return;
    setError(null);
    openSession.mutate(
      { site_id: siteId, montant_ouverture: Number(montantOuverture) },
      {
        onSuccess: (session) => setCreatedId(session.id),
        onError: (err) => setError(apiErrorMessage(err)),
      },
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Ouvrir une session de caisse</DialogTitle>
          <DialogDescription>
            Choisissez le site et le fonds de caisse de départ. La session sera ouverte pour vous.
          </DialogDescription>
        </DialogHeader>

        {createdId ? (
          <div className="space-y-4 py-4 text-center">
            <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success/10 text-success">
              <CheckCircle2 size={24} />
            </div>
            <p className="text-sm text-text">Session de caisse ouverte — #{createdId}</p>
          </div>
        ) : (
          <div className="space-y-4">
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
            <div>
              <Label>Montant d'ouverture</Label>
              <input
                type="number"
                min={0}
                step="0.01"
                value={montantOuverture}
                onChange={(e) => setMontantOuverture(e.target.value)}
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
          {createdId ? (
            <Button onClick={() => onOpenChange(false)}>Fermer</Button>
          ) : (
            <>
              <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={openSession.isPending}>
                Annuler
              </Button>
              <Button onClick={handleSubmit} disabled={!canSubmit || openSession.isPending}>
                {openSession.isPending && <LoaderCircle size={16} className="animate-spin" />}
                Ouvrir la session
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
