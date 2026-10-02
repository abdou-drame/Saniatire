import { useMutation } from "@tanstack/react-query";
import axios from "axios";
import { LoaderCircle, Lock, LogIn, ScrollText, ShieldCheck } from "lucide-react";
import { useState, type FormEvent } from "react";
import { useNavigate } from "react-router-dom";
import { AuthNotice, AuthSwitchLinks, EmailField, PasswordField } from "@/components/auth/auth-fields";
import { AuthShell, authButtonClass } from "@/components/auth/auth-shell";
import { BRAND_NAME } from "@/components/brand/brand-logo";
import { apiErrorMessage } from "@/lib/api-error";
import { Button } from "@/components/ui/button";
import { usePlatformAuth } from "@/hooks/use-platform-auth";
import { platformApi } from "@/lib/platform-api";
import type { PlatformAdmin } from "@/types/api";

interface LoginResponse {
  token: string;
  platform_admin: PlatformAdmin;
}

/**
 * Pas de lien "mot de passe oublié" ni d'inscription : ce compte unique est
 * géré exclusivement via `php artisan platform:create-admin`, jamais par un
 * flux self-service (voir PlatformAuthController).
 */
export function PlatformLoginPage() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [notice, setNotice] = useState<string | null>(null);
  const { login } = usePlatformAuth();
  const navigate = useNavigate();

  const mutation = useMutation({
    mutationFn: async () => {
      const { data } = await platformApi.post<LoginResponse>("/platform/login", { email, password });
      return data;
    },
    onSuccess: (data) => {
      login(data.token, data.platform_admin);
      navigate("/platform/structures", { replace: true });
    },
    onError: (error) => {
      if (axios.isAxiosError(error) && error.response?.status === 422) {
        setNotice("Identifiants invalides.");
        return;
      }
      // 429 (trop de tentatives) : message serveur explicite, affiché tel quel.
      setNotice(apiErrorMessage(error));
    },
  });

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setNotice(null);
    mutation.mutate();
  }

  return (
    <AuthShell
      tone="platform"
      badge={{ icon: ShieldCheck, label: `Accès réservé — Équipe ${BRAND_NAME}` }}
      tagline="Supervision des structures clientes de la plateforme."
      description={`Création de nouvelles structures, activation des modules, et consultation du journal d'audit global — réservé à l'équipe ${BRAND_NAME}.`}
      points={[
        { icon: ScrollText, label: "Modifications des structures journalisées" },
        { icon: Lock, label: "Aucun accès aux dossiers patients des structures" },
      ]}
      title="Administration plateforme"
      subtitle={`Connexion réservée à l'équipe ${BRAND_NAME}.`}
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        <EmailField
          id="email"
          label="Adresse e-mail"
          required
          autoComplete="username"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder="admin@plateforme.sante"
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

        {notice && <AuthNotice>{notice}</AuthNotice>}

        <Button type="submit" size="lg" className={authButtonClass("platform")} disabled={mutation.isPending}>
          {mutation.isPending ? <LoaderCircle size={16} className="animate-spin" /> : <LogIn size={16} />}
          Se connecter
        </Button>
      </form>

      <AuthSwitchLinks links={[{ to: "/login", label: "Retour à l'espace personnel de structure" }]} />
    </AuthShell>
  );
}
