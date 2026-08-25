import { useMutation } from "@tanstack/react-query";
import { Activity, LoaderCircle } from "lucide-react";
import { useState, type FormEvent } from "react";
import { Link, useNavigate, useSearchParams } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { patientApi } from "@/lib/patient-api";
import { portalErrorMessage } from "@/lib/portal-error";

export function PortalResetPasswordPage() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get("token") ?? "";
  const email = searchParams.get("email") ?? "";
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [notice, setNotice] = useState<string | null>(null);
  const navigate = useNavigate();

  const mutation = useMutation({
    mutationFn: async () => {
      await patientApi.post("/portail-patient/reinitialiser-mot-de-passe", {
        token,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
    },
    onSuccess: () => {
      navigate("/portail/login", {
        replace: true,
        state: { notice: "Mot de passe modifié. Vous pouvez vous connecter." },
      });
    },
    onError: (error) => setNotice(portalErrorMessage(error)),
  });

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setNotice(null);
    mutation.mutate();
  }

  if (!token || !email) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-bg px-4">
        <div className="w-full max-w-sm rounded-lg border border-border bg-surface p-6 text-center">
          <p className="text-sm text-text-muted">
            Ce lien de réinitialisation est incomplet. Utilisez le lien reçu par e-mail.
          </p>
          <Link to="/portail/login" className="mt-3 inline-block text-xs text-accent-light hover:underline">
            Retour à la connexion
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-bg px-4">
      <div className="w-full max-w-sm">
        <div className="mb-8 flex flex-col items-center gap-3">
          <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-accent text-white">
            <Activity size={22} strokeWidth={2.25} />
          </div>
          <div className="text-center">
            <h1 className="font-heading text-lg font-semibold text-text">Nouveau mot de passe</h1>
          </div>
        </div>

        <form
          onSubmit={handleSubmit}
          className="space-y-4 rounded-lg border border-border bg-surface p-6 shadow-[var(--shadow-card)]"
        >
          <div className="space-y-1.5">
            <label htmlFor="password" className="text-xs font-medium text-text-muted">
              Nouveau mot de passe
            </label>
            <input
              id="password"
              type="password"
              required
              minLength={8}
              autoComplete="new-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="h-10 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              placeholder="8 caractères minimum"
            />
          </div>

          <div className="space-y-1.5">
            <label htmlFor="password_confirmation" className="text-xs font-medium text-text-muted">
              Confirmer le mot de passe
            </label>
            <input
              id="password_confirmation"
              type="password"
              required
              autoComplete="new-password"
              value={passwordConfirmation}
              onChange={(e) => setPasswordConfirmation(e.target.value)}
              className="h-10 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              placeholder="••••••••"
            />
          </div>

          {notice && (
            <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-warning">
              {notice}
            </p>
          )}

          <Button type="submit" className="w-full" disabled={mutation.isPending}>
            {mutation.isPending && <LoaderCircle size={16} className="animate-spin" />}
            Valider
          </Button>
        </form>
      </div>
    </div>
  );
}
