import { useQueryClient } from "@tanstack/react-query";
import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { platformApi } from "@/lib/platform-api";
import { clearPlatformToken, getPlatformToken, setPlatformToken } from "@/lib/platform-token";
import type { PlatformAdmin } from "@/types/api";

type PlatformAuthContextValue = {
  isAuthenticated: boolean;
  isLoading: boolean;
  platformAdmin: PlatformAdmin | null;
  login: (token: string, platformAdmin: PlatformAdmin) => void;
  logout: () => void;
};

const PlatformAuthContext = createContext<PlatformAuthContextValue | null>(null);

export function PlatformAuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const [platformAdmin, setPlatformAdmin] = useState<PlatformAdmin | null>(null);
  const [isAuthenticated, setIsAuthenticated] = useState(() => Boolean(getPlatformToken()));
  const [isLoading, setIsLoading] = useState(() => Boolean(getPlatformToken()));

  useEffect(() => {
    if (!getPlatformToken()) {
      setIsLoading(false);
      return;
    }
    platformApi
      .get<{ data: PlatformAdmin }>("/platform/me")
      .then(({ data }) => {
        setPlatformAdmin(data.data);
        setIsAuthenticated(true);
      })
      .catch(() => {
        clearPlatformToken();
        setIsAuthenticated(false);
      })
      .finally(() => setIsLoading(false));
  }, []);

  function login(token: string, authenticatedPlatformAdmin: PlatformAdmin) {
    setPlatformToken(token);
    setPlatformAdmin(authenticatedPlatformAdmin);
    setIsAuthenticated(true);
  }

  function logout() {
    // Capturé avant le nettoyage local : l'intercepteur de requête lit le
    // jeton depuis le storage au moment de l'envoi, qui serait déjà vidé
    // si on attendait la résolution de l'appel avant de nettoyer l'état
    // local (ce qui créait une fenêtre où la navigation vers /login avait
    // déjà eu lieu sans que le jeton ne soit encore effacé).
    const token = getPlatformToken();
    clearPlatformToken();
    setPlatformAdmin(null);
    setIsAuthenticated(false);
    queryClient.clear();
    if (token) {
      platformApi
        .post("/platform/logout", undefined, { headers: { Authorization: `Bearer ${token}` } })
        .catch(() => {
          // Révocation serveur best-effort : le jeton est déjà purgé côté client.
        });
    }
  }

  return (
    <PlatformAuthContext.Provider value={{ isAuthenticated, isLoading, platformAdmin, login, logout }}>
      {children}
    </PlatformAuthContext.Provider>
  );
}

export function usePlatformAuth() {
  const context = useContext(PlatformAuthContext);
  if (!context) {
    throw new Error("usePlatformAuth must be used within a PlatformAuthProvider");
  }
  return context;
}
