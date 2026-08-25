import { useQueryClient } from "@tanstack/react-query";
import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { prescriberApi } from "@/lib/prescriber-api";
import { clearPrescriberToken, getPrescriberToken, setPrescriberToken } from "@/lib/prescriber-token";
import type { PrescriberUser } from "@/types/api";

type PrescriberAuthContextValue = {
  isAuthenticated: boolean;
  isLoading: boolean;
  prescriber: PrescriberUser | null;
  login: (token: string, prescriber: PrescriberUser) => void;
  logout: () => void;
};

const PrescriberAuthContext = createContext<PrescriberAuthContextValue | null>(null);

export function PrescriberAuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const [prescriber, setPrescriber] = useState<PrescriberUser | null>(null);
  const [isAuthenticated, setIsAuthenticated] = useState(() => Boolean(getPrescriberToken()));
  const [isLoading, setIsLoading] = useState(() => Boolean(getPrescriberToken()));

  useEffect(() => {
    if (!getPrescriberToken()) {
      setIsLoading(false);
      return;
    }
    prescriberApi
      .get<{ data: PrescriberUser }>("/portail-prescripteur/me")
      .then(({ data }) => {
        setPrescriber(data.data);
        setIsAuthenticated(true);
      })
      .catch(() => {
        clearPrescriberToken();
        setIsAuthenticated(false);
      })
      .finally(() => setIsLoading(false));
  }, []);

  function login(token: string, authenticatedPrescriber: PrescriberUser) {
    setPrescriberToken(token);
    setPrescriber(authenticatedPrescriber);
    setIsAuthenticated(true);
  }

  function logout() {
    // Capturé avant le nettoyage local : l'intercepteur de requête lit le
    // jeton depuis le storage au moment de l'envoi, qui serait déjà vidé
    // si on attendait la résolution de l'appel avant de nettoyer l'état
    // local (ce qui créait une fenêtre où la navigation vers /login avait
    // déjà eu lieu sans que le jeton ne soit encore effacé).
    const token = getPrescriberToken();
    clearPrescriberToken();
    setPrescriber(null);
    setIsAuthenticated(false);
    queryClient.clear();
    if (token) {
      prescriberApi
        .post("/portail-prescripteur/logout", undefined, { headers: { Authorization: `Bearer ${token}` } })
        .catch(() => {
          // Révocation serveur best-effort : le jeton est déjà purgé côté client.
        });
    }
  }

  return (
    <PrescriberAuthContext.Provider value={{ isAuthenticated, isLoading, prescriber, login, logout }}>
      {children}
    </PrescriberAuthContext.Provider>
  );
}

export function usePrescriberAuth() {
  const context = useContext(PrescriberAuthContext);
  if (!context) {
    throw new Error("usePrescriberAuth must be used within a PrescriberAuthProvider");
  }
  return context;
}
