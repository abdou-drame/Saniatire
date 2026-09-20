import { useMutation } from "@tanstack/react-query";
import { KeyRound, LoaderCircle } from "lucide-react";
import { useState, type FormEvent } from "react";
import { Button } from "@/components/ui/button";
import { FieldError, Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { api } from "@/lib/api";
import { apiErrorMessage } from "@/lib/api-error";

/**
 * Écran de changement de mot de passe obligatoire (must_change_password),
 * affiché avant tout autre accès pour un compte créé avec un mot de passe
 * généré (ex. premier administrateur d'une structure créé depuis l'espace
 * plateforme). Consomme POST /auth/change-password, sans vérification de
 * l'ancien mot de passe côté backend.
 */
export function RequiredPasswordChangePanel({ onChanged }: { onChanged: () => void }) {
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [error, setError] = useState<string | null>(null);

  const mutation = useMutation({
    mutationFn: async () => {
      await api.post("/auth/change-password", {
        password,
        password_confirmation: confirmation,
      });
    },
    onSuccess: () => onChanged(),
    onError: (err) => setError(apiErrorMessage(err)),
  });

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    if (password.length < 8) {
      setError("Le mot de passe doit contenir au moins 8 caractères.");
      return;
    }
    if (password !== confirmation) {
      setError("Les deux mots de passe ne correspondent pas.");
      return;
    }
    mutation.mutate();
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <p className="text-sm text-text-muted">
        Votre mot de passe a été généré automatiquement. Choisissez-en un nouveau avant de continuer.
      </p>

      <div>
        <Label htmlFor="new-password">Nouveau mot de passe</Label>
        <Input
          id="new-password"
          type="password"
          required
          autoComplete="new-password"
          autoFocus
          value={password}
          onChange={(e) => setPassword(e.target.value)}
        />
      </div>

      <div>
        <Label htmlFor="new-password-confirmation">Confirmer le mot de passe</Label>
        <Input
          id="new-password-confirmation"
          type="password"
          required
          autoComplete="new-password"
          value={confirmation}
          onChange={(e) => setConfirmation(e.target.value)}
        />
      </div>

      {error && <FieldError>{error}</FieldError>}

      <Button type="submit" className="w-full" disabled={mutation.isPending}>
        {mutation.isPending ? <LoaderCircle size={16} className="animate-spin" /> : <KeyRound size={16} />}
        Changer le mot de passe
      </Button>
    </form>
  );
}
