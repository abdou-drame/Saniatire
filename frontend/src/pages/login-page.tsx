import { useMutation } from "@tanstack/react-query";
import axios from "axios";
import { Activity, LoaderCircle, LogIn } from "lucide-react";
import { useState, type FormEvent } from "react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { SixDigitInput } from "@/components/two-factor/six-digit-input";
import { api } from "@/lib/api";
import { apiErrorMessage } from "@/lib/api-error";
import { useAuth } from "@/hooks/use-auth";
import type { AuthenticatedUser } from "@/types/api";

interface LoginResponse {
  token?: string;
  user?: AuthenticatedUser;
  two_factor_required?: boolean;
  two_factor_setup_required?: boolean;
  challenge?: string;
}

export function LoginPage() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [notice, setNotice] = useState<string | null>(null);
  const [step, setStep] = useState<"credentials" | "totp">("credentials");
  const [challenge, setChallenge] = useState<string | null>(null);
  const [useRecovery, setUseRecovery] = useState(false);
  const [recoveryCode, setRecoveryCode] = useState("");
  const [attempt, setAttempt] = useState(0);
  const { login } = useAuth();
  const navigate = useNavigate();

  const mutation = useMutation({
    mutationFn: async () => {
      const { data } = await api.post<LoginResponse>("/auth/login", { email, password });
      return data;
    },
    onSuccess: (data) => {
      if (data.two_factor_required) {
        setChallenge(data.challenge ?? null);
        setStep("totp");
        return;
      }
      if (data.token && data.user) {
        login(data.token, data.user);
        navigate("/dashboard", { replace: true });
      }
    },
    onError: (error) => {
      if (axios.isAxiosError(error) && error.response?.status === 422) {
        setNotice("Identifiants incorrects.");
        return;
      }
      setNotice(apiErrorMessage(error));
    },
  });

  const challengeMutation = useMutation({
    mutationFn: async (payload: { code?: string; recovery_code?: string }) => {
      const { data } = await api.post<LoginResponse>("/auth/2fa/challenge", { challenge, ...payload });
      return data;
    },
    onSuccess: (data) => {
      if (data.token && data.user) {
        login(data.token, data.user);
        navigate("/dashboard", { replace: true });
      }
    },
    onError: (error) => {
      setNotice(apiErrorMessage(error));
      setAttempt((a) => a + 1);
    },
  });

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setNotice(null);
    mutation.mutate();
  }

  function handleRecoverySubmit(event: FormEvent) {
    event.preventDefault();
    setNotice(null);
    challengeMutation.mutate({ recovery_code: recoveryCode });
  }

  function backToCredentials() {
    setStep("credentials");
    setChallenge(null);
    setNotice(null);
    setUseRecovery(false);
    setRecoveryCode("");
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-bg px-4">
      <div className="w-full max-w-sm">
        <div className="glow-accent relative mb-8 flex flex-col items-center gap-3 pb-2">
          <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-accent text-white">
            <Activity size={22} strokeWidth={2.25} />
          </div>
          <div className="relative text-center">
            <h1 className="font-heading text-lg font-semibold text-text">Sanitaire</h1>
            <p className="text-sm text-text-muted">Plateforme de gestion sanitaire</p>
          </div>
        </div>

        {step === "credentials" ? (
          <form
            onSubmit={handleSubmit}
            className="space-y-4 rounded-lg border border-border bg-surface p-6 shadow-[var(--shadow-card)]"
          >
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
                className="h-10 w-full rounded-md border border-border bg-bg px-3 text-sm text-text placeholder:text-text-subtle focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                placeholder="vous@structure.sante"
              />
            </div>

            <div className="space-y-1.5">
              <label htmlFor="password" className="text-xs font-medium text-text-muted">
                Mot de passe
              </label>
              <input
                id="password"
                type="password"
                required
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="h-10 w-full rounded-md border border-border bg-bg px-3 text-sm text-text placeholder:text-text-subtle focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                placeholder="••••••••"
              />
            </div>

            {notice && (
              <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-warning">
                {notice}
              </p>
            )}

            <Button type="submit" className="w-full" disabled={mutation.isPending}>
              {mutation.isPending ? (
                <LoaderCircle size={16} className="animate-spin" />
              ) : (
                <LogIn size={16} />
              )}
              Se connecter
            </Button>
          </form>
        ) : (
          <div className="space-y-4 rounded-lg border border-border bg-surface p-6 shadow-[var(--shadow-card)]">
            <div>
              <h2 className="font-heading text-base font-semibold text-text">Vérification en deux étapes</h2>
              <p className="mt-1 text-sm text-text-muted">
                {useRecovery
                  ? "Saisissez l'un de vos codes de récupération."
                  : "Saisissez le code à 6 chiffres généré par votre application d'authentification."}
              </p>
            </div>

            {!useRecovery ? (
              <SixDigitInput
                key={attempt}
                disabled={challengeMutation.isPending}
                onComplete={(code) => challengeMutation.mutate({ code })}
              />
            ) : (
              <form onSubmit={handleRecoverySubmit} className="space-y-3">
                <Input
                  placeholder="Code de récupération"
                  value={recoveryCode}
                  onChange={(e) => setRecoveryCode(e.target.value)}
                  disabled={challengeMutation.isPending}
                  autoFocus
                />
                <Button type="submit" className="w-full" disabled={challengeMutation.isPending || !recoveryCode}>
                  {challengeMutation.isPending && <LoaderCircle size={16} className="animate-spin" />}
                  Valider
                </Button>
              </form>
            )}

            {challengeMutation.isPending && !useRecovery && (
              <p className="flex items-center gap-1.5 text-xs text-text-muted">
                <LoaderCircle size={12} className="animate-spin" /> Vérification...
              </p>
            )}

            {notice && (
              <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-warning">
                {notice}
              </p>
            )}

            <div className="flex items-center justify-between text-xs">
              <button
                type="button"
                className="text-text-muted underline-offset-2 hover:underline"
                onClick={() => {
                  setUseRecovery((v) => !v);
                  setNotice(null);
                }}
              >
                {useRecovery ? "Utiliser le code de l'application" : "Utiliser un code de récupération"}
              </button>
              <button
                type="button"
                className="text-text-muted underline-offset-2 hover:underline"
                onClick={backToCredentials}
              >
                Retour
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
