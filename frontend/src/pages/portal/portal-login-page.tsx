import { useMutation } from "@tanstack/react-query";
import axios from "axios";
import { FlaskConical, LoaderCircle, LogIn, ShieldCheck } from "lucide-react";
import { useState, type FormEvent } from "react";
import { Link, useLocation, useNavigate } from "react-router-dom";
import { AuthNotice, AuthSwitchLinks, EmailField, PasswordField } from "@/components/auth/auth-fields";
import { AuthShell, authButtonClass } from "@/components/auth/auth-shell";
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
    <AuthShell
      tone="patient"
      tagline="Votre suivi médical, toujours à portée de main."
      description="Rendez-vous, résultats de laboratoire et d'imagerie, documents médicaux — consultez tout, où que vous soyez, en toute confidentialité."
      points={[
        { icon: ShieldCheck, label: "Vos données restent strictement confidentielles" },
        { icon: FlaskConical, label: "Résultats transmis uniquement une fois validés" },
      ]}
      title="Espace patient"
      subtitle="Connectez-vous pour accéder à votre suivi."
    >
      <form onSubmit={handleSubmit} className="space-y-4">
        <EmailField
          id="email"
          label="Adresse e-mail"
          required
          autoComplete="username"
          value={email}
          onChange={(e) => setEmail(e.target.value)}
          placeholder="vous@exemple.com"
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
          <Link to="/portail/mot-de-passe-oublie" className="text-text-muted hover:text-accent-light">
            Mot de passe oublié ?
          </Link>
        </div>

        {(location.state?.notice || notice) && <AuthNotice>{notice ?? location.state?.notice}</AuthNotice>}

        <Button type="submit" size="lg" className={authButtonClass("patient")} disabled={mutation.isPending}>
          {mutation.isPending ? <LoaderCircle size={16} className="animate-spin" /> : <LogIn size={16} />}
          Se connecter
        </Button>

        <p className="text-center text-xs text-text-muted">
          Première connexion ?{" "}
          <Link to="/portail/activer" className="text-accent-light hover:underline">
            Activer mon compte
          </Link>
        </p>
      </form>

      <AuthSwitchLinks links={[{ to: "/login", label: "Vous êtes un professionnel de santé ?" }]} />
    </AuthShell>
  );
}
