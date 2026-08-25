import { useMutation } from "@tanstack/react-query";
import axios from "axios";
import { Activity, LoaderCircle, LogIn } from "lucide-react";
import { useState, type FormEvent } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { usePatientAuth } from "@/hooks/use-patient-auth";
import { patientApi } from "@/lib/patient-api";
import type { PatientUser } from "@/types/api";

interface LoginResponse {
  token: string;
  patient: PatientUser;
}

export function PortalLoginPage() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [notice, setNotice] = useState<string | null>(null);
  const { login } = usePatientAuth();
  const navigate = useNavigate();
  const location = useLocation() as { state?: { notice?: string } };

  const mutation = useMutation({
    mutationFn: async () => {
      const { data } = await patientApi.post<LoginResponse>("/portail-patient/login", { email, password });
      return data;
    },
    onSuccess: (data) => {
      login(data.token, data.patient);
      navigate("/portail/rendez-vous", { replace: true });
    },
    onError: (error) => {
      if (axios.isAxiosError(error) && error.response?.status === 422) {
        setNotice("Identifiant ou mot de passe incorrect.");
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
    <div className="flex min-h-screen items-center justify-center bg-bg px-4">
      <div className="w-full max-w-sm">
        <div className="glow-accent relative mb-8 flex flex-col items-center gap-3 pb-2">
          <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-accent text-white">
            <Activity size={22} strokeWidth={2.25} />
          </div>
          <div className="relative text-center">
            <h1 className="font-heading text-lg font-semibold text-text">Sanitaire</h1>
            <p className="text-sm text-text-muted">Espace patient</p>
          </div>
        </div>

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
              placeholder="vous@exemple.com"
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

          {(location.state?.notice || notice) && (
            <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-warning">
              {notice ?? location.state?.notice}
            </p>
          )}

          <Button type="submit" className="w-full" disabled={mutation.isPending}>
            {mutation.isPending ? <LoaderCircle size={16} className="animate-spin" /> : <LogIn size={16} />}
            Se connecter
          </Button>

          <div className="flex items-center justify-between pt-1 text-xs">
            <Link to="/portail/mot-de-passe-oublie" className="text-text-muted hover:text-accent-light">
              Mot de passe oublié ?
            </Link>
            <Link to="/portail/activer" className="text-text-muted hover:text-accent-light">
              Activer mon compte
            </Link>
          </div>
        </form>
      </div>
    </div>
  );
}
