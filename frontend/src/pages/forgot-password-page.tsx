import { useMutation } from "@tanstack/react-query";
import { Building2, LoaderCircle, Mail, ScrollText } from "lucide-react";
import { useState, type FormEvent } from "react";
import { Link } from "react-router-dom";
import { AuthNotice, EmailField } from "@/components/auth/auth-fields";
import { AuthShell, authButtonClass } from "@/components/auth/auth-shell";
import { Button } from "@/components/ui/button";
import { api } from "@/lib/api";

const POINTS = [
  { icon: Building2, label: "Données de votre structure, strictement isolées" },
  { icon: ScrollText, label: "Actions journalisées, sans possibilité de modification" },
];

/**
 * « Mot de passe oublié » du personnel. Même message quelle que soit
 * l'issue : l'écran ne révèle jamais si un compte existe pour l'adresse.
 */
export function ForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [sent, setSent] = useState(false);

  const mutation = useMutation({
    mutationFn: async () => {
      await api.post("/auth/forgot-password", { email });
    },
    onSuccess: () => setSent(true),
    onError: () => setSent(true),
  });

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    mutation.mutate();
  }

  return (
    <AuthShell
      tone="staff"
      tagline="L'espace de travail de votre structure, réuni en un seul endroit."
      description="Recevez par e-mail un lien pour choisir un nouveau mot de passe."
      points={POINTS}
      title="Mot de passe oublié"
      subtitle="Indiquez l'adresse e-mail de votre compte."
    >
      {sent ? (
        <AuthNotice>
          Si un compte existe avec cette adresse, un lien de réinitialisation vient de vous être envoyé.
        </AuthNotice>
      ) : (
        <form onSubmit={handleSubmit} className="space-y-4">
          <EmailField
            id="email"
            label="Adresse e-mail"
            required
            autoComplete="username"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="vous@structure.com"
          />
          <Button type="submit" size="lg" className={authButtonClass("staff")} disabled={mutation.isPending}>
            {mutation.isPending ? <LoaderCircle size={16} className="animate-spin" /> : <Mail size={16} />}
            Envoyer le lien
          </Button>
        </form>
      )}
      <Link to="/login" className="mt-4 block text-center text-xs text-accent-light hover:underline">
        Retour à la connexion
      </Link>
    </AuthShell>
  );
}
