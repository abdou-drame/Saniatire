import { Activity, CalendarDays, FileText, LogOut, MessageSquareWarning, Receipt, Settings } from "lucide-react";
import { NavLink, Outlet, useNavigate } from "react-router-dom";
import { usePatientAuth } from "@/hooks/use-patient-auth";
import { cn } from "@/lib/utils";

const NAV_ITEMS = [
  { href: "/portail/rendez-vous", label: "Mes rendez-vous", icon: CalendarDays },
  { href: "/portail/documents", label: "Mes documents", icon: FileText },
  { href: "/portail/factures", label: "Mes factures", icon: Receipt },
  { href: "/portail/reclamations", label: "Mes réclamations", icon: MessageSquareWarning },
  { href: "/portail/preferences", label: "Mes préférences", icon: Settings },
];

export function PortalLayout() {
  const { patient, logout } = usePatientAuth();
  const navigate = useNavigate();

  function handleLogout() {
    logout();
    navigate("/portail/login", { replace: true });
  }

  return (
    <div className="min-h-screen bg-bg">
      <header className="border-b border-border bg-surface">
        <div className="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-3">
          <div className="flex items-center gap-2.5">
            <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-accent text-white">
              <Activity size={18} strokeWidth={2.25} />
            </div>
            <div>
              <p className="font-heading text-sm font-semibold text-text">Sanitaire</p>
              <p className="text-xs text-text-muted">
                {patient ? `${patient.first_name} ${patient.last_name}` : "Espace patient"}
              </p>
            </div>
          </div>
          <button
            type="button"
            onClick={handleLogout}
            className="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm text-text-muted transition-colors hover:bg-surface-hover hover:text-text"
          >
            <LogOut size={15} />
            Déconnexion
          </button>
        </div>
        <nav className="mx-auto flex max-w-3xl gap-1 overflow-x-auto px-4 pb-2">
          {NAV_ITEMS.map((item) => (
            <NavLink
              key={item.href}
              to={item.href}
              className={({ isActive }) =>
                cn(
                  "flex items-center gap-1.5 whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium transition-colors",
                  isActive ? "bg-accent/10 text-accent-light" : "text-text-muted hover:bg-surface-hover hover:text-text",
                )
              }
            >
              <item.icon size={15} />
              {item.label}
            </NavLink>
          ))}
        </nav>
      </header>
      <main className="mx-auto max-w-3xl px-4 py-6">
        <Outlet />
      </main>
    </div>
  );
}
