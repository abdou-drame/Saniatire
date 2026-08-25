import { useMutation } from "@tanstack/react-query";
import { Activity, LoaderCircle } from "lucide-react";
import { useState, type FormEvent } from "react";
import { Link } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { patientApi } from "@/lib/patient-api";

export function PortalForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [sent, setSent] = useState(false);

  const mutation = useMutation({
    mutationFn: async () => {
      await patientApi.post("/portail-patient/mot-de-passe-oublie", { email });
    },
    onSuccess: () => setSent(true),
    onError: () => setSent(true), // même message quelle que soit l'issue : ne jamais révéler si l'e-mail existe
  });

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    mutation.mutate();
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-bg px-4">
      <div className="w-full max-w-sm">
        <div className="mb-8 flex flex-col items-center gap-3">
          <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-accent text-white">
            <Activity size={22} strokeWidth={2.25} />
          </div>
          <div className="text-center">
            <h1 className="font-heading text-lg font-semibold text-text">Mot de passe oublié</h1>
            <p className="text-sm text-text-muted">Recevez un lien pour choisir un nouveau mot de passe.</p>
          </div>
        </div>

        <div className="space-y-4 rounded-lg border border-border bg-surface p-6 shadow-[var(--shadow-card)]">
          {sent ? (
            <p className="text-sm text-text">
              Si un compte existe avec cette adresse, un e-mail contenant un lien de réinitialisation vient de
              vous être envoyé.
            </p>
          ) : (
            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="space-y-1.5">
                <label htmlFor="email" className="text-xs font-medium text-text-muted">
                  Adresse e-mail
                </label>
                <input
                  id="email"
                  type="email"
                  required
                  autoComplete="username"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  className="h-10 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                  placeholder="vous@exemple.com"
                />
              </div>
              <Button type="submit" className="w-full" disabled={mutation.isPending}>
                {mutation.isPending && <LoaderCircle size={16} className="animate-spin" />}
                Envoyer le lien
              </Button>
            </form>
          )}
          <Link to="/portail/login" className="block text-center text-xs text-accent-light hover:underline">
            Retour à la connexion
          </Link>
        </div>
      </div>
    </div>
  );
}
