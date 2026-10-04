import { useMutation } from "@tanstack/react-query";
import axios from "axios";
import { Building2, LoaderCircle, LogIn, ScrollText } from "lucide-react";
import { useState, type FormEvent } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { AuthNotice, AuthSwitchLinks, EmailField, PasswordField } from "@/components/auth/auth-fields";
import { AuthShell, authButtonClass } from "@/components/auth/auth-shell";
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
  const location = useLocation() as { state?: { notice?: string } };
  const [notice, setNotice] = useState<string | null>(location.state?.notice ?? null);
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
    <AuthShell
      tone="staff"
      tagline="L'espace de travail de votre structure, réuni en un seul endroit."
      description="Consultations, rendez-vous, laboratoire, imagerie, hospitalisation, facturation et pharmacie — tout ce dont votre équipe a besoin au quotidien."
      points={[
        { icon: Building2, label: "Données de votre structure, strictement isolées" },
        { icon: ScrollText, label: "Actions journalisées, sans possibilité de modification" },
      ]}
      title="Connexion"
      subtitle="Accédez à l'espace de travail de votre structure."
    >
      {step === "credentials" ? (
        <form onSubmit={handleSubmit} className="space-y-4">
          <EmailField
            id="email"
            label="Adresse e-mail"
            required
            autoComplete="username"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="vous@structure.sante"
          />

          <PasswordField
            id="password"
            label="Mot de passe"
            required
            autoComplete="current-password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="••••••••"
          />

          <p className="text-right text-xs">
            <Link to="/mot-de-passe-oublie" className="text-accent-light hover:underline">
              Mot de passe oublié ?
            </Link>
          </p>

          {notice && <AuthNotice>{notice}</AuthNotice>}

          <Button type="submit" size="lg" className={authButtonClass("staff")} disabled={mutation.isPending}>
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

          {notice && <AuthNotice>{notice}</AuthNotice>}

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

      <AuthSwitchLinks
        links={[
          { to: "/portail/login", label: "Je suis un patient" },
          { to: "/portail-prescripteur/login", label: "Je suis un prescripteur externe" },
        ]}
      />
    </AuthShell>
  );
}
