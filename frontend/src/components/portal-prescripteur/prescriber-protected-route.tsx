import type { ReactNode } from "react";
import { Navigate } from "react-router-dom";
import { usePrescriberAuth } from "@/hooks/use-prescriber-auth";

export function PrescriberProtectedRoute({ children }: { children: ReactNode }) {
  const { isAuthenticated, isLoading } = usePrescriberAuth();

  if (isLoading) {
    return (
      <div className="flex h-screen items-center justify-center bg-bg">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-border-strong border-t-accent" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/portail-prescripteur/login" replace />;
  }

  return <>{children}</>;
}
