import { useMutation } from "@tanstack/react-query";
import axios from "axios";
import { FlaskConical, LoaderCircle, LogIn, Stethoscope, UserCheck } from "lucide-react";
import { useState, type FormEvent } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { AuthNotice, AuthSwitchLinks, EmailField, PasswordField } from "@/components/auth/auth-fields";
import { AuthShell, authButtonClass } from "@/components/auth/auth-shell";
import { apiErrorMessage } from "@/lib/api-error";
import { Button } from "@/components/ui/button";
import { usePrescriberAuth } from "@/hooks/use-prescriber-auth";
import { prescriberApi } from "@/lib/prescriber-api";
import type { PrescriberUser } from "@/types/api";

interface LoginResponse {
  token: string;
  prescriber: PrescriberUser;
}

export function PrescriberLoginPage() {
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [notice, setNotice] = useState<string | null>(null);
  const { login } = usePrescriberAuth();
  const navigate = useNavigate();
  const location = useLocation() as { state?: { notice?: string } };

  const mutation = useMutation({
    mutationFn: async () => {
      const { data } = await prescriberApi.post<LoginResponse>("/portail-prescripteur/login", { email, password });
      return data;
    },
    onSuccess: (data) => {
      login(data.token, data.prescriber);
      navigate("/portail-prescripteur/demandes", { replace: true });
    },
    onError: (error) => {
      if (axios.isAxiosError(error) && error.response?.status === 422) {
        setNotice("Identifiant ou mot de passe incorrect.");
        return;
      }
      // 403 (structure suspendue ou archivée) et 429 (trop de tentatives) :
      // le message serveur est explicite et affiché tel quel.
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
      tone="prescriber"
      badge={{ icon: Stethoscope, label: "Prescripteur externe" }}
      tagline="Suivez vos demandes d'examens, en toute transparence."
      description="Adressez vos demandes de laboratoire et d'imagerie à cette structure et suivez leur avancement, depuis un espace qui vous est dédié."
      points={[
        { icon: UserCheck, label: "Accès limité à vos propres demandes" },
        { icon: FlaskConical, label: "Résultats visibles uniquement une fois transmis" },
      ]}
      title="Espace prescripteur"
      subtitle="Connectez-vous pour suivre vos demandes."
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        <EmailField
          id="email"
          label="Adresse e-mail"
          required
          autoComplete="username"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder="vous@cabinet.com"
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

        <div className="text-right text-xs">
          <Link to="/portail-prescripteur/mot-de-passe-oublie" className="text-text-muted hover:text-accent-light">
            Mot de passe oublié ?
          </Link>
        </div>

        {(location.state?.notice || notice) && <AuthNotice>{notice ?? location.state?.notice}</AuthNotice>}

        <Button type="submit" size="lg" className={authButtonClass("prescriber")} disabled={mutation.isPending}>
          {mutation.isPending ? <LoaderCircle size={16} className="animate-spin" /> : <LogIn size={16} />}
          Se connecter
        </Button>

        <p className="text-center text-xs text-text-muted">
          Première connexion ?{" "}
          <Link to="/portail-prescripteur/activer" className="text-accent-light hover:underline">
            Activer mon compte
          </Link>
        </p>
      </form>

      <AuthSwitchLinks links={[{ to: "/login", label: "Vous faites partie d'une structure ?" }]} />
    </AuthShell>
  );
}
