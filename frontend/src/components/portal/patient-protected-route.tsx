import type { ReactNode } from "react";
import { Navigate } from "react-router-dom";
import { usePatientAuth } from "@/hooks/use-patient-auth";

export function PatientProtectedRoute({ children }: { children: ReactNode }) {
  const { isAuthenticated, isLoading } = usePatientAuth();

  if (isLoading) {
    return (
      <div className="flex h-screen items-center justify-center bg-bg">
        <div className="h-8 w-8 animate-spin rounded-full border-2 border-border-strong border-t-accent" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/portail/login" replace />;
  }

  return <>{children}</>;
}
