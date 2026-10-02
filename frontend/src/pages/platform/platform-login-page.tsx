import { useMutation } from "@tanstack/react-query";
import axios from "axios";
import { ArrowLeft, LoaderCircle, Lock, LogIn, Mail, ScrollText, ShieldCheck } from "lucide-react";
import { useState, type FormEvent } from "react";
import { Link, useNavigate } from "react-router-dom";
import { BRAND_NAME, BrandLogo } from "@/components/brand/brand-logo";
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
      setNotice("Impossible de contacter le serveur. Réessayez.");
    },
  });

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setNotice(null);
    mutation.mutate();
  }

  return (
    <div className="relative flex min-h-screen items-center justify-center overflow-hidden bg-bg px-4 py-10">
      {/* Halo violet, propre à cet espace réservé. */}
      <div
        aria-hidden="true"
        className="pointer-events-none absolute inset-x-0 top-0 h-[520px] bg-[radial-gradient(ellipse_55%_60%_at_50%_0%,color-mix(in_srgb,var(--color-accent2)_22%,transparent),transparent_70%)]"
      />

      <div className="relative w-full max-w-sm">
        <div className="mb-7 flex flex-col items-center gap-4">
          <BrandLogo className="h-[92px]" />
          <span className="inline-flex items-center gap-1.5 rounded-full border border-accent2/40 bg-accent2/10 px-3 py-1 font-mono text-[11px] uppercase tracking-wider text-accent2-light">
            <ShieldCheck size={13} />
            Accès réservé
          </span>
          <div className="text-center">
            <h1 className="font-heading text-xl font-semibold text-text">Administration plateforme</h1>
            <p className="mt-1 text-sm text-text-muted">
              Espace de l'équipe {BRAND_NAME} — supervision des structures clientes.
            </p>
          </div>
        </div>

        <form
          onSubmit={handleSubmit}
          className="space-y-4 rounded-xl border border-border bg-surface p-6 shadow-[var(--shadow-card)]"
        >
          <div className="space-y-1.5">
            <label htmlFor="email" className="text-xs font-medium text-text-muted">
              Adresse e-mail
            </label>
            <div className="relative">
              <Mail size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-text-subtle" />
              <input
                id="email"
                type="email"
                required
                autoComplete="username"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                className="h-10 w-full rounded-md border border-border bg-bg pl-9 pr-3 text-sm text-text placeholder:text-text-subtle focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent2"
                placeholder="admin@plateforme.sante"
              />
            </div>
          </div>

          <div className="space-y-1.5">
            <label htmlFor="password" className="text-xs font-medium text-text-muted">
              Mot de passe
            </label>
            <div className="relative">
              <Lock size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-text-subtle" />
              <input
                id="password"
                type="password"
                required
                autoComplete="current-password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                className="h-10 w-full rounded-md border border-border bg-bg pl-9 pr-3 text-sm text-text placeholder:text-text-subtle focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent2"
                placeholder="••••••••"
              />
            </div>
          </div>

          {notice && (
            <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-warning">
              {notice}
            </p>
          )}

          <Button
            type="submit"
            size="lg"
            className="w-full bg-gradient-to-r from-accent to-accent2 hover:brightness-110"
            disabled={mutation.isPending}
          >
            {mutation.isPending ? <LoaderCircle size={16} className="animate-spin" /> : <LogIn size={16} />}
            Se connecter
          </Button>

          <p className="flex items-start gap-2 border-t border-border pt-4 text-xs leading-relaxed text-text-subtle">
            <ScrollText size={14} className="mt-0.5 shrink-0" />
            Les actions effectuées depuis cet espace sont enregistrées dans le journal d'audit.
          </p>
        </form>

        <Link
          to="/login"
          className="mt-6 flex items-center justify-center gap-1.5 text-sm text-text-muted transition-colors hover:text-text"
        >
          <ArrowLeft size={15} />
          Retour à l'espace personnel
        </Link>
      </div>
    </div>
  );
}
