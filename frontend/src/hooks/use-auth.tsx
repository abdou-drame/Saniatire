import { useQueryClient } from "@tanstack/react-query";
import { createContext, useContext, useEffect, useState, type ReactNode } from "react";
import { api } from "@/lib/api";
import { clearToken, getToken, setToken } from "@/lib/token";
import type { AuthenticatedUser } from "@/types/api";

type AuthContextValue = {
  isAuthenticated: boolean;
  isLoading: boolean;
  user: AuthenticatedUser | null;
  login: (token: string, user: AuthenticatedUser) => void;
  logout: () => void;
  refreshUser: () => Promise<void>;
  hasRole: (role: string) => boolean;
  hasPermission: (permission: string) => boolean;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const [user, setUser] = useState<AuthenticatedUser | null>(null);
  const [isAuthenticated, setIsAuthenticated] = useState(() => Boolean(getToken()));
  const [isLoading, setIsLoading] = useState(() => Boolean(getToken()));

  useEffect(() => {
    if (!getToken()) {
      setIsLoading(false);
      return;
    }
    api
      .get<{ data: AuthenticatedUser }>("/auth/me")
      .then(({ data }) => {
        setUser(data.data);
        setIsAuthenticated(true);
      })
      .catch(() => {
        clearToken();
        setIsAuthenticated(false);
      })
      .finally(() => setIsLoading(false));
  }, []);

  function login(token: string, authenticatedUser: AuthenticatedUser) {
    setToken(token);
    setUser(authenticatedUser);
    setIsAuthenticated(true);
  }

  function logout() {
    // Capturé avant le nettoyage local : l'intercepteur de requête lit le
    // jeton depuis le storage au moment de l'envoi, qui serait déjà vidé
    // si on attendait la résolution de l'appel avant de nettoyer l'état
    // local (ce qui créait une fenêtre où la navigation vers /login avait
    // déjà eu lieu sans que le jeton ne soit encore effacé).
    const token = getToken();
    clearToken();
    setUser(null);
    setIsAuthenticated(false);
    queryClient.clear();
    if (token) {
      api.post("/auth/logout", undefined, { headers: { Authorization: `Bearer ${token}` } }).catch(() => {
        // Révocation serveur best-effort : le jeton est déjà purgé côté client.
      });
    }
  }

  async function refreshUser() {
    const { data } = await api.get<{ data: AuthenticatedUser }>("/auth/me");
    setUser(data.data);
  }

  function hasRole(role: string) {
    return user?.roles.includes(role) ?? false;
  }

  function hasPermission(permission: string) {
    return user?.permissions.includes(permission) ?? false;
  }

  return (
    <AuthContext.Provider
      value={{ isAuthenticated, isLoading, user, login, logout, refreshUser, hasRole, hasPermission }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) {
    throw new Error("useAuth must be used within an AuthProvider");
  }
  return context;
}
