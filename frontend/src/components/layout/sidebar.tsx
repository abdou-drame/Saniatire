import type { LucideIcon } from "lucide-react";
import { X } from "lucide-react";
import { useEffect, useRef, type KeyboardEvent as ReactKeyboardEvent } from "react";
import { NavLink } from "react-router-dom";
import { BRAND_NAME, BrandMark } from "@/components/brand/brand-logo";
import { cn } from "@/lib/utils";

export interface NavItem {
  label: string;
  href: string;
  icon: LucideIcon;
  /** Modules (clés ModuleCatalog) dont un seul actif suffit à afficher l'entrée ; absent = socle, toujours visible. */
  modules?: string[];
  /** Rôles dont un seul suffit à afficher l'entrée ; absent = tous les rôles. */
  roles?: string[];
}

export interface NavSection {
  title?: string;
  items: NavItem[];
}

export interface SidebarProps {
  sections: NavSection[];
  structureName?: string;
}

/**
 * Contenu commun à la barre latérale fixe (grand écran) et au tiroir
 * (petit écran) : les deux affichent exactement les mêmes liens.
 */
function SidebarContent({ sections, structureName = BRAND_NAME, onNavigate }: SidebarProps & { onNavigate?: () => void }) {
  return (
    <div className="flex h-full flex-col">
      <div className="glow-accent relative flex items-center gap-2.5 border-b border-border px-5 py-5">
        <BrandMark className="relative h-8 w-8 rounded-md" />
        <span className="relative font-heading text-sm font-semibold text-text">
          {structureName}
        </span>
      </div>

      <nav aria-label="Navigation principale" className="flex-1 space-y-5 overflow-y-auto px-3 py-4">
        {sections.map((section, index) => (
          <div key={section.title ?? index}>
            {section.title && (
              <p className="mb-1.5 px-2.5 text-[11px] font-medium uppercase tracking-wider text-text-subtle">
                {section.title}
              </p>
            )}
            <ul className="space-y-0.5">
              {section.items.map((item) => (
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
                        <item.icon size={17} strokeWidth={2} className="shrink-0" />
                        {item.label}
                      </>
                    )}
                  </NavLink>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </nav>
    </div>
  );
}

/** Barre latérale fixe — affichée à partir de lg (1024px). */
export function Sidebar(props: SidebarProps) {
  return (
    <aside className="hidden h-screen w-[230px] shrink-0 border-r border-border bg-surface lg:block">
      <SidebarContent {...props} />
    </aside>
  );
}

const FOCUSABLE = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';

export interface SidebarDrawerProps extends SidebarProps {
  open: boolean;
  onClose: () => void;
}

/**
 * Tiroir de navigation en dessous de lg, ouvert par le bouton menu de la
 * barre du haut. Il se superpose au contenu (overlay) au lieu de le
 * pousser. Même comportement que le tiroir de l'administration
 * plateforme : focus sur « Fermer », Échap ferme, focus piégé, défilement
 * de la page bloqué, fermeture au choix d'un lien.
 */
export function SidebarDrawer({ open, onClose, ...props }: SidebarDrawerProps) {
  const drawerRef = useRef<HTMLDivElement>(null);
  const closeButtonRef = useRef<HTMLButtonElement>(null);

  useEffect(() => {
    if (!open) return;
    closeButtonRef.current?.focus();
    const previousOverflow = document.body.style.overflow;
    document.body.style.overflow = "hidden";
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === "Escape") onClose();
    };
    document.addEventListener("keydown", onKeyDown);
    return () => {
      document.body.style.overflow = previousOverflow;
      document.removeEventListener("keydown", onKeyDown);
    };
  }, [open, onClose]);

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
    <div className={cn("lg:hidden", !open && "pointer-events-none")}>
      <div
        aria-hidden="true"
        onClick={onClose}
        className={cn(
          "fixed inset-0 z-40 bg-black/60 transition-opacity duration-200",
          open ? "opacity-100" : "opacity-0",
        )}
      />
      <div
        ref={drawerRef}
        id="app-drawer"
        role="dialog"
        aria-modal="true"
        aria-label="Menu de navigation"
        inert={!open}
        onKeyDown={trapFocus}
        className={cn(
          "fixed inset-y-0 left-0 z-50 w-[280px] max-w-[85vw] border-r border-border bg-surface shadow-xl transition-transform duration-200 ease-out",
          open ? "translate-x-0" : "-translate-x-full",
        )}
      >
        <button
          ref={closeButtonRef}
          type="button"
          onClick={onClose}
          aria-label="Fermer le menu"
          className="absolute right-3 top-5 z-10 rounded-md p-1.5 text-text-muted hover:bg-surface-hover hover:text-text focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent"
        >
          <X size={18} />
        </button>
        <SidebarContent {...props} onNavigate={onClose} />
      </div>
    </div>
  );
}
