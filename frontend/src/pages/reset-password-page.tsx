import { useMutation } from "@tanstack/react-query";
import { Building2, KeyRound, LoaderCircle, ScrollText } from "lucide-react";
import { useState, type FormEvent } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import { AuthNotice, PasswordField } from "@/components/auth/auth-fields";
import { AuthShell, authButtonClass } from "@/components/auth/auth-shell";
import { Button } from "@/components/ui/button";
import { api } from "@/lib/api";
import { apiErrorMessage } from "@/lib/api-error";

const POINTS = [
  { icon: Building2, label: "Données de votre structure, strictement isolées" },
  { icon: ScrollText, label: "Actions journalisées, sans possibilité de modification" },
];

/**
 * Cible du lien reçu par e-mail (AppServiceProvider::configureStaffPasswordReset).
 * Toutes les sessions ouvertes sont fermées côté serveur après le changement.
 */
export function ResetPasswordPage() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get("token") ?? "";
  const email = searchParams.get("email") ?? "";
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [notice, setNotice] = useState<string | null>(null);
  const navigate = useNavigate();

  const mutation = useMutation({
    mutationFn: async () => {
      await api.post("/auth/reset-password", {
        token,
        email,
        password,
        password_confirmation: confirmation,
      });
    },
    onSuccess: () => {
      navigate("/login", {
        replace: true,
        state: { notice: "Mot de passe modifié. Vous pouvez vous connecter." },
      });
    },
    onError: (error) => setNotice(apiErrorMessage(error)),
  });

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setNotice(null);
    if (password !== confirmation) {
      setNotice("Les deux mots de passe ne correspondent pas.");
      return;
    }
    mutation.mutate();
  }

  return (
    <AuthShell
      tone="staff"
      tagline="L'espace de travail de votre structure, réuni en un seul endroit."
      description="Choisissez un nouveau mot de passe pour votre compte."
      points={POINTS}
      title="Nouveau mot de passe"
      subtitle={email || "Lien de réinitialisation"}
    >
      {!token || !email ? (
        <AuthNotice>Ce lien de réinitialisation est incomplet. Utilisez le lien reçu par e-mail.</AuthNotice>
      ) : (
        <form onSubmit={handleSubmit} className="space-y-4">
          <PasswordField
            id="password"
            label="Nouveau mot de passe"
            required
            minLength={8}
            autoComplete="new-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="8 caractères minimum"
          />
          <PasswordField
            id="password_confirmation"
            label="Confirmer le mot de passe"
            required
            autoComplete="new-password"
            value={confirmation}
            onChange={(e) => setConfirmation(e.target.value)}
            placeholder="••••••••"
          />
          {notice && <AuthNotice>{notice}</AuthNotice>}
          <Button type="submit" size="lg" className={authButtonClass("staff")} disabled={mutation.isPending}>
            {mutation.isPending ? <LoaderCircle size={16} className="animate-spin" /> : <KeyRound size={16} />}
            Valider
          </Button>
        </form>
      )}
      <Link to="/login" className="mt-4 block text-center text-xs text-accent-light hover:underline">
        Retour à la connexion
      </Link>
    </AuthShell>
  );
}
