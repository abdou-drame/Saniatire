import { BarChart3, Building2, ChevronRight, ClipboardList, LogOut, Menu, ShieldCheck, Tags, X } from "lucide-react";
import { useEffect, useRef, useState, type KeyboardEvent as ReactKeyboardEvent } from "react";
import { Link, NavLink, Outlet, useLocation, useNavigate } from "react-router-dom";
import { BRAND_NAME, BrandMark } from "@/components/brand/brand-logo";
import { PlatformInitials } from "@/components/platform/platform-ui";
import { usePlatformAuth } from "@/hooks/use-platform-auth";
import { cn } from "@/lib/utils";

const NAV_ITEMS = [
  { href: "/platform/structures", label: "Structures", icon: Building2 },
  { href: "/platform/formules", label: "Formules", icon: Tags },
  { href: "/platform/stats", label: "Statistiques", icon: BarChart3 },
  { href: "/platform/audit", label: "Journal d'audit plateforme", icon: ClipboardList },
];

/** Fil d'Ariane de la barre supérieure, déduit de l'URL. */
function breadcrumbFor(pathname: string): { label: string; href?: string }[] {
  const section = NAV_ITEMS.find((item) => pathname === item.href || pathname.startsWith(`${item.href}/`));
  if (!section) return [{ label: "Administration" }];
  if (section.href === "/platform/structures" && /^\/platform\/structures\/[^/]+/.test(pathname)) {
    return [{ label: section.label, href: section.href }, { label: "Fiche structure" }];
  }
  return [{ label: section.label }];
}

const FOCUSABLE = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';

function SidebarContent({
  adminName,
  adminEmail,
  onNavigate,
  onLogout,
}: {
  adminName: string;
  adminEmail: string | null;
  onNavigate?: () => void;
  onLogout: () => void;
}) {
  return (
    <div className="flex h-full flex-col">
      <div className="glow-accent relative flex items-center gap-3 border-b border-border px-5 py-5">
        <BrandMark className="relative h-9 w-9 rounded-lg" />
        <div className="relative min-w-0">
          <p className="truncate font-heading text-sm font-semibold text-text">{BRAND_NAME}</p>
          <p className="mt-0.5 inline-flex items-center gap-1 rounded-full border border-accent2/30 bg-accent2/10 px-2 py-px text-[10px] font-semibold uppercase tracking-wider text-accent2-light">
            <ShieldCheck size={10} strokeWidth={2.5} />
            Super Admin
          </p>
        </div>
      </div>

      <nav aria-label="Navigation plateforme" className="flex-1 overflow-y-auto px-3 py-4">
        <p className="mb-1.5 px-2.5 text-[11px] font-medium uppercase tracking-wider text-text-subtle">Plateforme</p>
        <ul className="space-y-0.5">
          {NAV_ITEMS.map((item) => (
            <li key={item.href}>
              <NavLink
                to={item.href}
                onClick={onNavigate}
                className={({ isActive }) =>
                  cn(
                    "relative flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm font-medium text-text-muted transition-colors",
                    "hover:bg-surface-hover hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent",
                    isActive && "bg-surface-hover text-text",
                  )
                }
              >
                {({ isActive }) => (
                  <>
                    {isActive && (
                      <span className="absolute -left-3 top-1/2 h-[18px] w-[3px] -translate-y-1/2 rounded-full bg-accent" />
                    )}
                    <item.icon size={17} strokeWidth={2} className={isActive ? "text-accent-light" : undefined} />
                    <span className="leading-snug">{item.label}</span>
                  </>
                )}
              </NavLink>
            </li>
          ))}
        </ul>
      </nav>

      <div className="border-t border-border p-3">
        <div className="flex items-center gap-2.5 rounded-md px-2 py-2">
          <PlatformInitials name={adminName} className="h-8 w-8 rounded-full" />
          <div className="min-w-0 flex-1 leading-tight">
            <p className="truncate text-sm font-medium text-text">{adminName}</p>
            <p className="truncate text-xs text-text-subtle">{adminEmail ?? "Administration plateforme"}</p>
          </div>
        </div>
        <button
          type="button"
          onClick={onLogout}
          className="mt-1 flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-sm font-medium text-text-muted transition-colors hover:bg-danger/10 hover:text-danger focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
        >
          <LogOut size={16} />
          Déconnexion
        </button>
      </div>
    </div>
  );
}

/**
 * Coque de l'administration plateforme : sidebar fixe sur grand écran,
 * tiroir hors-champ (bouton menu) en dessous de lg. Un seul acteur
 * (l'administrateur de plateforme), quatre sections.
 */
export function PlatformLayout() {
  const { platformAdmin, logout } = usePlatformAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const menuButtonRef = useRef<HTMLButtonElement>(null);
  const drawerRef = useRef<HTMLDivElement>(null);
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const wasOpen = useRef(false);

  const adminName = platformAdmin?.name ?? "Administrateur";
  const crumbs = breadcrumbFor(location.pathname);

  function handleLogout() {
    setDrawerOpen(false);
    logout();
    navigate("/platform/login", { replace: true });
  }

  // Tiroir ouvert : focus sur le bouton de fermeture, Échap ferme, défilement
  // de la page bloqué. À la fermeture, le focus revient au bouton menu.
  useEffect(() => {
    if (drawerOpen) {
      wasOpen.current = true;
      closeButtonRef.current?.focus();
      const previousOverflow = document.body.style.overflow;
      document.body.style.overflow = "hidden";
      const onKeyDown = (event: KeyboardEvent) => {
        if (event.key === "Escape") setDrawerOpen(false);
      };
      document.addEventListener("keydown", onKeyDown);
      return () => {
        document.body.style.overflow = previousOverflow;
        document.removeEventListener("keydown", onKeyDown);
      };
    }
    if (wasOpen.current) {
      wasOpen.current = false;
      menuButtonRef.current?.focus();
    }
  }, [drawerOpen]);

  /** Garde le focus clavier à l'intérieur du tiroir ouvert. */
  function trapFocus(event: ReactKeyboardEvent<HTMLDivElement>) {
    if (event.key !== "Tab" || !drawerRef.current) return;
    const focusable = Array.from(drawerRef.current.querySelectorAll<HTMLElement>(FOCUSABLE));
    if (focusable.length === 0) return;
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  }

  return (
    <div className="min-h-screen bg-bg">
      {/* Sidebar fixe — grand écran */}
      <aside className="fixed inset-y-0 left-0 z-30 hidden w-[248px] border-r border-border bg-surface lg:block">
        <SidebarContent adminName={adminName} adminEmail={platformAdmin?.email ?? null} onLogout={handleLogout} />
      </aside>

      {/* Tiroir — petit écran */}
      <div className={cn("lg:hidden", !drawerOpen && "pointer-events-none")}>
        <div
          aria-hidden="true"
          onClick={() => setDrawerOpen(false)}
          className={cn(
            "fixed inset-0 z-40 bg-black/60 transition-opacity duration-200",
            drawerOpen ? "opacity-100" : "opacity-0",
          )}
        />
        <div
          ref={drawerRef}
          id="platform-drawer"
          role="dialog"
          aria-modal="true"
          aria-label="Menu de navigation"
          inert={!drawerOpen}
          onKeyDown={trapFocus}
          className={cn(
            "fixed inset-y-0 left-0 z-50 w-[280px] max-w-[85vw] border-r border-border bg-surface shadow-xl transition-transform duration-200 ease-out",
            drawerOpen ? "translate-x-0" : "-translate-x-full",
          )}
        >
          <button
            ref={closeButtonRef}
            type="button"
            onClick={() => setDrawerOpen(false)}
            aria-label="Fermer le menu"
            className="absolute right-3 top-5 z-10 rounded-md p-1.5 text-text-muted hover:bg-surface-hover hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
          >
            <X size={18} />
          </button>
          <SidebarContent
            adminName={adminName}
            adminEmail={platformAdmin?.email ?? null}
            onNavigate={() => setDrawerOpen(false)}
            onLogout={handleLogout}
          />
        </div>
      </div>

      <div className="flex min-h-screen min-w-0 flex-col lg:pl-[248px]">
        <header className="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b border-border bg-bg/85 px-4 backdrop-blur sm:px-6 lg:px-8">
          <button
            ref={menuButtonRef}
            type="button"
            onClick={() => setDrawerOpen(true)}
            aria-label="Ouvrir le menu"
            aria-expanded={drawerOpen}
            aria-controls="platform-drawer"
            className="-ml-1.5 rounded-md p-1.5 text-text-muted hover:bg-surface-hover hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent lg:hidden"
          >
            <Menu size={20} />
          </button>
          <nav aria-label="Fil d'Ariane" className="min-w-0 flex-1">
            <ol className="flex min-w-0 items-center gap-1.5 text-sm">
              <li className="hidden shrink-0 text-text-subtle sm:block">Plateforme</li>
              {crumbs.map((crumb, index) => (
                <li key={crumb.label} className="flex min-w-0 items-center gap-1.5">
                  <ChevronRight
                    size={14}
                    aria-hidden="true"
                    className={cn("shrink-0 text-text-subtle", index === 0 && "hidden sm:block")}
                  />
                  {crumb.href ? (
                    <Link to={crumb.href} className="truncate text-text-muted hover:text-text">
                      {crumb.label}
                    </Link>
                  ) : (
                    <span className="truncate font-medium text-text" aria-current="page">
                      {crumb.label}
                    </span>
                  )}
                </li>
              ))}
            </ol>
          </nav>
          <BrandMark className="h-7 w-7 rounded-md lg:hidden" />
        </header>

        <main className="mx-auto w-full min-w-0 max-w-7xl flex-1 px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
