import type { ReactNode } from "react";
import { Navigate } from "react-router-dom";
import { TwoFactorSetupPanel } from "@/components/two-factor/two-factor-setup-panel";
import { useAuth } from "@/hooks/use-auth";

export function ProtectedRoute({ children }: { children: ReactNode }) {
  const { isAuthenticated, isLoading, user, refreshUser } = useAuth();

  if (isLoading) {
    return (
      <div className="flex h-screen items-center justify-center bg-bg">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-border-strong border-t-accent" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  if (user && user.two_factor_required && !user.two_factor_enabled) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-bg px-4">
        <div className="w-full max-w-sm space-y-4 rounded-lg border border-border bg-surface p-6 shadow-[var(--shadow-card)]">
          <div>
            <h1 className="font-heading text-base font-semibold text-text">Double authentification requise</h1>
            <p className="mt-1 text-sm text-text-muted">
              Votre rôle exige l'activation de la 2FA avant de continuer.
            </p>
          </div>
          <TwoFactorSetupPanel onActivated={() => refreshUser()} />
        </div>
      </div>
    );
  }

  return <>{children}</>;
}
