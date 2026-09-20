import type { ReactNode } from "react";
import { Navigate } from "react-router-dom";
import { usePlatformAuth } from "@/hooks/use-platform-auth";

export function PlatformProtectedRoute({ children }: { children: ReactNode }) {
  const { isAuthenticated, isLoading } = usePlatformAuth();

  if (isLoading) {
    return (
      <div className="flex h-screen items-center justify-center bg-bg">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-border-strong border-t-accent" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/platform/login" replace />;
  }

  return <>{children}</>;
}
