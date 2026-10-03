import type { LucideIcon } from "lucide-react";
import type { ReactNode } from "react";
import { Card } from "@/components/ui/card";
import { Skeleton } from "@/components/ui/loading-state";
import { cn } from "@/lib/utils";

/**
 * Briques de mise en page propres à l'administration plateforme. Elles ne
 * remplacent pas les composants partagés de components/ui (utilisés par
 * toute l'application du personnel) : elles les composent.
 */

export interface PlatformPageHeaderProps {
  title: ReactNode;
  description?: ReactNode;
  icon?: LucideIcon;
  /** Actions principales, alignées à droite sur grand écran. */
  actions?: ReactNode;
  /** Contenu affiché au-dessus du titre (lien retour, fil d'Ariane…). */
  eyebrow?: ReactNode;
  className?: string;
}

export function PlatformPageHeader({ title, description, icon: Icon, actions, eyebrow, className }: PlatformPageHeaderProps) {
  return (
    <div className={cn("space-y-3", className)}>
      {eyebrow}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div className="flex min-w-0 items-start gap-3">
          {Icon && (
            <div className="hidden h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-accent/25 bg-accent/10 text-accent-light sm:flex">
              <Icon size={19} strokeWidth={2} />
            </div>
          )}
          <div className="min-w-0">
            <h1 className="font-heading text-xl font-semibold text-text sm:text-2xl">{title}</h1>
            {description && <p className="mt-1 max-w-2xl text-sm text-text-muted">{description}</p>}
          </div>
        </div>
        {actions && <div className="flex flex-wrap items-center gap-2 sm:shrink-0 sm:justify-end">{actions}</div>}
      </div>
    </div>
  );
}

type StatTone = "accent" | "success" | "warning" | "danger" | "neutral";

const TONE_ICON: Record<StatTone, string> = {
  accent: "bg-accent/10 text-accent-light",
  success: "bg-success/10 text-success",
  warning: "bg-warning/10 text-warning",
  danger: "bg-danger/10 text-danger",
  neutral: "bg-surface-hover text-text-muted",
};

export interface PlatformStatProps {
  label: string;
  value: ReactNode;
  icon?: LucideIcon;
  tone?: StatTone;
  hint?: ReactNode;
  className?: string;
}

/** Indicateur compact (bandeau de synthèse en haut de page). */
export function PlatformStat({ label, value, icon: Icon, tone = "neutral", hint, className }: PlatformStatProps) {
  return (
    <Card className={cn("flex min-w-0 items-center gap-3 p-4", className)}>
      {Icon && (
        <div className={cn("flex h-10 w-10 shrink-0 items-center justify-center rounded-lg", TONE_ICON[tone])}>
          <Icon size={18} strokeWidth={2} />
        </div>
      )}
      <div className="min-w-0">
        <p className="truncate text-xs font-medium uppercase tracking-wide text-text-subtle">{label}</p>
        <p className="font-heading font-tabular text-xl font-semibold leading-tight text-text">{value}</p>
        {hint && <p className="truncate text-xs text-text-muted">{hint}</p>}
      </div>
    </Card>
  );
}

export function PlatformStatSkeleton({ count = 4 }: { count?: number }) {
  return (
    <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
      {Array.from({ length: count }).map((_, i) => (
        <div key={i} className="flex items-center gap-3 rounded-lg border border-border bg-surface p-4">
          <Skeleton className="h-10 w-10 shrink-0 rounded-lg" />
          <div className="flex-1 space-y-2">
            <Skeleton className="h-3 w-20" />
            <Skeleton className="h-5 w-10" />
          </div>
        </div>
      ))}
    </div>
  );
}

/** Pastille d'initiales (structure, administrateur) — purement décorative. */
export function PlatformInitials({ name, className }: { name: string; className?: string }) {
  const initials = name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join("");
  return (
    <span
      aria-hidden="true"
      className={cn(
        "inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-accent/25 bg-accent/10 font-heading text-xs font-semibold text-accent-light",
        className,
      )}
    >
      {initials || "?"}
    </span>
  );
}

/** Note d'information discrète (bas de carte, aide contextuelle). */
export function PlatformNote({ icon: Icon, children, className }: { icon?: LucideIcon; children: ReactNode; className?: string }) {
  return (
    <p className={cn("flex items-start gap-1.5 text-xs text-text-subtle", className)}>
      {Icon && <Icon size={13} className="mt-0.5 shrink-0" />}
      <span>{children}</span>
    </p>
  );
}
