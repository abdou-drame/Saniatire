import { Activity, ClipboardList, LogOut, PlusCircle } from "lucide-react";
import { NavLink, Outlet, useNavigate } from "react-router-dom";
import { usePrescriberAuth } from "@/hooks/use-prescriber-auth";
import { cn } from "@/lib/utils";

const NAV_ITEMS = [
  { href: "/portail-prescripteur/demandes/nouvelle", label: "Nouvelle demande", icon: PlusCircle },
  { href: "/portail-prescripteur/demandes", label: "Mes demandes", icon: ClipboardList },
];

export function PrescriberPortalLayout() {
  const { prescriber, logout } = usePrescriberAuth();
  const navigate = useNavigate();

  function handleLogout() {
    logout();
    navigate("/portail-prescripteur/login", { replace: true });
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
                {prescriber ? `Dr ${prescriber.nom}` : "Espace prescripteur"}
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
