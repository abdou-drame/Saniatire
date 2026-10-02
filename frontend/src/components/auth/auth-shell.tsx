import type { LucideIcon } from "lucide-react";
import type { CSSProperties, ReactNode } from "react";
import { BRAND_NAME, BrandLogo } from "@/components/brand/brand-logo";
import { cn } from "@/lib/utils";

export type AuthTone = "staff" | "patient" | "prescriber" | "platform";

/** Teintes du halo et du bouton propres à chaque portail. */
const TONES: Record<AuthTone, { from: string; to: string; button: string }> = {
  staff: {
    from: "var(--color-accent)",
    to: "var(--color-accent2)",
    button: "bg-gradient-to-r from-accent to-accent2",
  },
  patient: {
    from: "var(--color-accent2)",
    to: "var(--color-accent3)",
    button: "bg-gradient-to-r from-accent3-dark to-accent2",
  },
  prescriber: {
    from: "var(--color-accent3)",
    to: "var(--color-accent)",
    button: "bg-gradient-to-r from-accent3-dark to-accent",
  },
  platform: {
    from: "var(--color-accent2)",
    to: "var(--color-accent2)",
    button: "bg-gradient-to-r from-accent to-accent2",
  },
};

/** Classes du bouton de connexion pleine largeur, dans la teinte du portail. */
export function authButtonClass(tone: AuthTone) {
  return cn("w-full text-white hover:brightness-110", TONES[tone].button);
}

function haloStyle(tone: AuthTone): CSSProperties {
  const { from, to } = TONES[tone];
  return {
    backgroundImage: [
      `radial-gradient(ellipse 70% 55% at 0% 0%, color-mix(in srgb, ${from} 24%, transparent), transparent 70%)`,
      `radial-gradient(ellipse 60% 50% at 100% 100%, color-mix(in srgb, ${to} 18%, transparent), transparent 70%)`,
    ].join(", "),
  };
}

/** Hachures diagonales très discrètes, réservées à l'espace plateforme. */
const HATCH_STYLE: CSSProperties = {
  backgroundImage:
    "repeating-linear-gradient(135deg, color-mix(in srgb, var(--color-text) 3%, transparent) 0 1px, transparent 1px 14px)",
};

export interface AuthPoint {
  icon: LucideIcon;
  label: string;
}

interface AuthShellProps {
  tone: AuthTone;
  /** Badge mono au-dessus de l'accroche (prescripteur, plateforme). */
  badge?: { icon?: LucideIcon; label: string };
  tagline: string;
  description: string;
  points: AuthPoint[];
  /** Titre et sous-titre de la colonne formulaire. */
  title: string;
  subtitle: string;
  children: ReactNode;
}

function Badge({ tone, badge }: { tone: AuthTone; badge: NonNullable<AuthShellProps["badge"]> }) {
  const Icon = badge.icon;
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1.5 rounded-full border px-3 py-1 font-mono text-[11px] uppercase tracking-wider",
        tone === "platform"
          ? "border-accent2/40 bg-accent2/10 text-accent2-light"
          : "border-accent3/40 bg-accent3/10 text-accent3-light",
      )}
    >
      {Icon && <Icon size={13} />}
      {badge.label}
    </span>
  );
}

/**
 * Mise en page commune aux 4 pages de connexion : panneau de marque à gauche
 * (à partir de `lg`), formulaire à droite. Sous `lg`, le panneau est masqué
 * et remplacé par un en-tête compact (logo, badge, accroche) au-dessus du
 * formulaire.
 */
export function AuthShell({ tone, badge, tagline, description, points, title, subtitle, children }: AuthShellProps) {
  const isPlatform = tone === "platform";

  return (
    <div className="flex min-h-screen bg-bg">
      <aside
        className={cn(
          "relative hidden w-[46%] max-w-[600px] shrink-0 flex-col justify-between overflow-hidden border-r border-border px-12 py-10 lg:flex xl:px-16",
          isPlatform ? "bg-surface-hover" : "bg-surface",
        )}
      >
        <div aria-hidden="true" className="pointer-events-none absolute inset-0" style={haloStyle(tone)} />
        {isPlatform && <div aria-hidden="true" className="pointer-events-none absolute inset-0" style={HATCH_STYLE} />}

        <BrandLogo className="relative h-[78px] self-start" />

        <div className="relative max-w-md space-y-5">
          {badge && <Badge tone={tone} badge={badge} />}
          <p className="font-heading text-[30px] font-semibold leading-tight tracking-tight text-text">{tagline}</p>
          <p className="text-[14.5px] leading-relaxed text-text-muted">{description}</p>
          <ul className="space-y-3 pt-2">
            {points.map(({ icon: Icon, label }) => (
              <li key={label} className="flex items-center gap-3 text-sm text-text">
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-border-strong bg-bg/60 text-text-muted">
                  <Icon size={15} />
                </span>
                {label}
              </li>
            ))}
          </ul>
        </div>

        <p className="relative text-xs text-text-subtle">© 2026 {BRAND_NAME} — Sénégal</p>
      </aside>

      <main className="flex flex-1 items-center justify-center px-4 py-10 sm:px-8">
        <div className="w-full max-w-sm">
          <div className="mb-8 flex flex-col items-center gap-4 text-center lg:hidden">
            <BrandLogo className="h-16" />
            {badge && <Badge tone={tone} badge={badge} />}
            <p className="font-heading text-lg font-semibold leading-snug text-text">{tagline}</p>
          </div>

          <div className="mb-6">
            <h1 className="font-heading text-2xl font-semibold text-text">{title}</h1>
            <p className="mt-1 text-sm text-text-muted">{subtitle}</p>
          </div>

          {children}
        </div>
      </main>
    </div>
  );
}
