import { useQueryClient } from "@tanstack/react-query";
import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { patientApi } from "@/lib/patient-api";
import { clearPatientToken, getPatientToken, setPatientToken } from "@/lib/patient-token";
import type { PatientUser } from "@/types/api";

type PatientAuthContextValue = {
  isAuthenticated: boolean;
  isLoading: boolean;
  patient: PatientUser | null;
  login: (token: string, patient: PatientUser) => void;
  logout: () => void;
};

const PatientAuthContext = createContext<PatientAuthContextValue | null>(null);

export function PatientAuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const [patient, setPatient] = useState<PatientUser | null>(null);
  const [isAuthenticated, setIsAuthenticated] = useState(() => Boolean(getPatientToken()));
  const [isLoading, setIsLoading] = useState(() => Boolean(getPatientToken()));

  useEffect(() => {
    if (!getPatientToken()) {
      setIsLoading(false);
      return;
    }
    patientApi
      .get<{ data: PatientUser }>("/portail-patient/me")
      .then(({ data }) => {
        setPatient(data.data);
        setIsAuthenticated(true);
      })
      .catch(() => {
        clearPatientToken();
        setIsAuthenticated(false);
      })
      .finally(() => setIsLoading(false));
  }, []);

  function login(token: string, authenticatedPatient: PatientUser) {
    setPatientToken(token);
    setPatient(authenticatedPatient);
    setIsAuthenticated(true);
  }

  function logout() {
    // Capturé avant le nettoyage local : l'intercepteur de requête lit le
    // jeton depuis le storage au moment de l'envoi, qui serait déjà vidé
    // si on attendait la résolution de l'appel avant de nettoyer l'état
    // local (ce qui créait une fenêtre où la navigation vers /portail/login
    // avait déjà eu lieu sans que le jeton ne soit encore effacé).
    const token = getPatientToken();
    clearPatientToken();
    setPatient(null);
    setIsAuthenticated(false);
    queryClient.clear();
    if (token) {
      patientApi
        .post("/portail-patient/logout", undefined, { headers: { Authorization: `Bearer ${token}` } })
        .catch(() => {
          // Révocation serveur best-effort : le jeton est déjà purgé côté client.
        });
    }
  }

  return (
    <PatientAuthContext.Provider value={{ isAuthenticated, isLoading, patient, login, logout }}>
      {children}
    </PatientAuthContext.Provider>
  );
}

export function usePatientAuth() {
  const context = useContext(PatientAuthContext);
  if (!context) {
    throw new Error("usePatientAuth must be used within a PatientAuthProvider");
  }
  return context;
}
