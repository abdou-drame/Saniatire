import type { LucideIcon } from "lucide-react";
import { Activity } from "lucide-react";
import { NavLink } from "react-router-dom";
import { cn } from "@/lib/utils";

export interface NavItem {
  label: string;
  href: string;
  icon: LucideIcon;
}

export interface NavSection {
  title?: string;
  items: NavItem[];
}

export interface SidebarProps {
  sections: NavSection[];
  structureName?: string;
}

export function Sidebar({ sections, structureName = "Sanitaire" }: SidebarProps) {
  return (
    <aside className="flex h-screen w-[230px] shrink-0 flex-col border-r border-border bg-surface">
      <div className="glow-accent relative flex items-center gap-2.5 border-b border-border px-5 py-5">
        <div className="flex h-8 w-8 items-center justify-center rounded-md bg-accent text-white">
          <Activity size={18} strokeWidth={2.25} />
        </div>
        <span className="relative font-heading text-sm font-semibold text-text">
          {structureName}
        </span>
      </div>

      <nav className="flex-1 space-y-5 overflow-y-auto px-3 py-4">
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
                    className={({ isActive }) =>
                      cn(
                        "relative flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm font-medium text-text-muted transition-colors",
                        "hover:bg-surface-hover hover:text-text",
                        isActive && "bg-surface-hover text-text",
                      )
                    }
                  >
                    {({ isActive }) => (
                      <>
                        {isActive && (
                          <span className="absolute -left-3 top-1/2 h-[18px] w-[3px] -translate-y-1/2 rounded-full bg-accent" />
                        )}
                        <item.icon size={17} strokeWidth={2} />
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
    </aside>
  );
}
