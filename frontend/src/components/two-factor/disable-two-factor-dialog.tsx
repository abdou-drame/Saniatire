import { useMutation } from "@tanstack/react-query";
import { LoaderCircle } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { api } from "@/lib/api";
import { apiErrorMessage } from "@/lib/api-error";

interface DisableTwoFactorDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onDisabled: () => void;
}

export function DisableTwoFactorDialog({ open, onOpenChange, onDisabled }: DisableTwoFactorDialogProps) {
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: async () => api.post("/auth/2fa/disable", { password }),
    onSuccess: () => {
      setPassword("");
      setError(null);
      onOpenChange(false);
      onDisabled();
    },
    onError: (err) => setError(apiErrorMessage(err)),
  });

  function handleOpenChange(next: boolean) {
    if (!next) {
      setPassword("");
      setError(null);
    }
    onOpenChange(next);
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Désactiver la double authentification</DialogTitle>
          <DialogDescription>
            Confirmez votre mot de passe pour désactiver la 2FA sur ce compte.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-1.5">
          <label htmlFor="disable-2fa-password" className="text-xs font-medium text-text-muted">
            Mot de passe
          </label>
          <Input
            id="disable-2fa-password"
            type="password"
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            disabled={mutation.isPending}
          />
        </div>

        {error && (
          <p className="mt-2 rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
            {error}
          </p>
        )}

        <DialogFooter>
          <Button variant="secondary" onClick={() => handleOpenChange(false)} disabled={mutation.isPending}>
            Annuler
          </Button>
          <Button variant="danger" onClick={() => mutation.mutate()} disabled={mutation.isPending || !password}>
            {mutation.isPending && <LoaderCircle size={14} className="animate-spin" />}
            Désactiver
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
