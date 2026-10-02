import type { ReactNode } from "react";
import { Link } from "react-router-dom";
import {
  Activity,
  ArrowRight,
  BedDouble,
  CalendarClock,
  FileHeart,
  FlaskConical,
  KeyRound,
  Layers,
  Link2,
  Pill,
  Receipt,
  ScrollText,
  ShieldCheck,
  Stethoscope,
  UserRound,
  type LucideIcon,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Card } from "@/components/ui/card";

/**
 * Adresse de contact commercial. Volontairement vide tant qu'aucune adresse
 * réelle n'a été choisie : le bouton mailto n'est rendu que si elle est
 * renseignée.
 */
const CONTACT_EMAIL: string | null = null;

/** Routes de connexion existantes des 4 guards (cf. App.tsx). */
export const LOGIN_ROUTES = {
  staff: "/login",
  patient: "/portail/login",
  prescriber: "/portail-prescripteur/login",
  platform: "/platform/login",
} as const;

const NAV_LINKS = [
  { href: "#plateforme", label: "Plateforme" },
  { href: "#securite", label: "Sécurité" },
  { href: "#portails", label: "Portails" },
  { href: "#contact", label: "Contact" },
];

const TRUST_BADGES = [
  "Isolation des données par structure",
  "Journal d'audit inviolable",
  "Codification CIM-10 / CIM-11",
];

interface Portal {
  icon: LucideIcon;
  title: string;
  description: string;
  cta: string;
  to: string;
}

const PORTALS: Portal[] = [
  {
    icon: Stethoscope,
    title: "Personnel de la structure",
    description:
      "Médecins, infirmiers, secrétariat, direction, laboratoire, pharmacie, comptabilité — chaque rôle accède exactement à ce dont il a besoin.",
    cta: "Connexion personnel",
    to: LOGIN_ROUTES.staff,
  },
  {
    icon: UserRound,
    title: "Espace Patient",
    description:
      "Consultez vos rendez-vous, vos résultats de laboratoire et d'imagerie, et vos documents médicaux en toute confidentialité.",
    cta: "Connexion patient",
    to: LOGIN_ROUTES.patient,
  },
  {
    icon: Link2,
    title: "Prescripteurs externes",
    description:
      "Praticiens extérieurs à la structure qui adressent ou suivent un patient référé — accès limité et tracé à ce patient.",
    cta: "Connexion prescripteur",
    to: LOGIN_ROUTES.prescriber,
  },
];

interface Feature {
  icon: LucideIcon;
  title: string;
  description: string;
}

const FEATURES: Feature[] = [
  {
    icon: CalendarClock,
    title: "Rendez-vous & accueil",
    description: "Agenda des praticiens, file d'attente et accueil des patients au même endroit.",
  },
  {
    icon: FileHeart,
    title: "Dossier patient unifié",
    description: "Antécédents, consultations et diagnostics codifiés réunis dans un dossier unique.",
  },
  {
    icon: FlaskConical,
    title: "Laboratoire & imagerie",
    description: "Demandes d'examens, saisie et validation des résultats, comptes rendus d'imagerie.",
  },
  {
    icon: BedDouble,
    title: "Hospitalisation & bloc opératoire",
    description: "Gestion des lits, des séjours et de la planification des interventions.",
  },
  {
    icon: Receipt,
    title: "Facturation & assurances",
    description: "Facturation des actes, caisse, prises en charge et suivi des créances.",
  },
  {
    icon: Pill,
    title: "Pharmacie & stocks",
    description: "Dispensation, mouvements de stock et approvisionnement en médicaments.",
  },
];

const SECURITY_POINTS: Feature[] = [
  {
    icon: Layers,
    title: "Isolation multi-structures",
    description: "Les données de chaque structure sont cloisonnées : aucune ne peut accéder à celles d'une autre.",
  },
  {
    icon: ScrollText,
    title: "Audit inviolable",
    description: "Chaque action sensible est consignée dans un journal chaîné qui ne peut être ni modifié ni effacé.",
  },
  {
    icon: KeyRound,
    title: "Authentification renforcée",
    description: "Espaces de connexion distincts par profil et double authentification pour les comptes sensibles.",
  },
];

function Logo() {
  return (
    <span className="flex items-center gap-2.5">
      <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-accent text-white">
        <Activity size={17} strokeWidth={2.25} />
      </span>
      <span className="font-heading text-base font-semibold text-text">Sanitaire</span>
    </span>
  );
}

function IconTile({ icon: Icon, tone = "accent" }: { icon: LucideIcon; tone?: "accent" | "accent2" }) {
  return (
    <span
      className={
        tone === "accent"
          ? "flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-accent/10 text-accent-light"
          : "flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-accent2/10 text-accent2-light"
      }
    >
      <Icon size={20} />
    </span>
  );
}

function SectionHeading({ eyebrow, title, children }: { eyebrow: string; title: string; children?: ReactNode }) {
  return (
    <div className="mx-auto mb-10 max-w-2xl text-center">
      <p className="mb-3 font-mono text-xs uppercase tracking-widest text-accent-light">{eyebrow}</p>
      <h2 className="font-heading text-2xl font-semibold text-text sm:text-3xl">{title}</h2>
      {children && <p className="mt-3 text-sm text-text-muted sm:text-base">{children}</p>}
    </div>
  );
}

export function LandingPage() {
  return (
    <div className="min-h-screen bg-bg text-text">
      <header className="fixed inset-x-0 top-0 z-50 border-b border-border/60 bg-bg/80 backdrop-blur">
        <nav className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6">
          <a href="#top" aria-label="Sanitaire — haut de page">
            <Logo />
          </a>
          <div className="hidden items-center gap-7 md:flex">
            {NAV_LINKS.map((link) => (
              <a
                key={link.href}
                href={link.href}
                className="text-sm text-text-muted transition-colors hover:text-text"
              >
                {link.label}
              </a>
            ))}
          </div>
          <Button asChild size="sm">
            <a href="#portails">Se connecter</a>
          </Button>
        </nav>
      </header>

      <main id="top">
        {/* Hero */}
        <section className="glow-accent relative overflow-hidden px-4 pb-20 pt-36 sm:px-6 sm:pt-44">
          <div className="relative mx-auto max-w-3xl text-center">
            <span className="inline-flex items-center gap-2 rounded-full border border-border bg-surface px-3 py-1 font-mono text-[11px] uppercase tracking-wider text-text-muted">
              <span className="h-1.5 w-1.5 rounded-full bg-accent" />
              Plateforme de gestion sanitaire — Sénégal
            </span>
            <h1 className="mt-6 font-heading text-4xl font-semibold leading-tight tracking-tight text-text sm:text-5xl md:text-6xl">
              Toute la gestion de votre structure de santé,{" "}
              <span className="bg-gradient-to-r from-accent to-accent2 bg-clip-text text-transparent">
                dans un seul espace.
              </span>
            </h1>
            <p className="mx-auto mt-6 max-w-2xl text-base text-text-muted sm:text-lg">
              Cabinets médicaux, laboratoires, cliniques et groupes de santé privés gèrent patients,
              consultations, laboratoire, imagerie, hospitalisation, facturation et pharmacie depuis une
              seule plateforme.
            </p>
            <div className="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
              <Button asChild size="lg" className="w-full sm:w-auto">
                <a href="#portails">
                  Accéder à mon espace
                  <ArrowRight size={16} />
                </a>
              </Button>
              <Button asChild variant="secondary" size="lg" className="w-full sm:w-auto">
                <a href="#contact">Présenter Sanitaire à ma structure</a>
              </Button>
            </div>
            <ul className="mt-10 flex flex-wrap items-center justify-center gap-x-6 gap-y-3">
              {TRUST_BADGES.map((badge) => (
                <li key={badge} className="flex items-center gap-2 text-xs text-text-muted">
                  <ShieldCheck size={14} className="text-success" />
                  {badge}
                </li>
              ))}
            </ul>
          </div>
        </section>

        {/* Portails */}
        <section id="portails" className="scroll-mt-20 px-4 py-20 sm:px-6">
          <div className="mx-auto max-w-6xl">
            <SectionHeading eyebrow="Portails" title="Choisissez votre espace">
              Chaque profil dispose de son propre espace de connexion.
            </SectionHeading>

            <div className="grid gap-5 md:grid-cols-3">
              {PORTALS.map((portal) => (
                <Card key={portal.to} className="flex flex-col p-6 transition-colors hover:border-border-strong">
                  <IconTile icon={portal.icon} />
                  <h3 className="mt-5 font-heading text-lg font-semibold text-text">{portal.title}</h3>
                  <p className="mt-2 flex-1 text-sm leading-relaxed text-text-muted">{portal.description}</p>
                  <Button asChild className="mt-6 w-full">
                    <Link to={portal.to}>
                      {portal.cta}
                      <ArrowRight size={15} />
                    </Link>
                  </Button>
                </Card>
              ))}
            </div>

            <div className="mt-8 flex flex-col gap-4 rounded-lg border border-dashed border-border-strong bg-bg px-5 py-4 sm:flex-row sm:items-center">
              <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-border text-text-subtle">
                <ShieldCheck size={18} />
              </span>
              <div className="flex-1">
                <p className="flex flex-wrap items-center gap-2 text-sm font-medium text-text-muted">
                  Administration plateforme
                  <span className="rounded border border-border px-1.5 py-0.5 font-mono text-[10px] uppercase tracking-wider text-text-subtle">
                    Accès réservé
                  </span>
                </p>
                <p className="mt-0.5 text-xs text-text-subtle">
                  Réservé à l'équipe Sanitaire — supervision et activation des structures clientes.
                </p>
              </div>
              <Button asChild variant="ghost" size="sm" className="self-start sm:self-auto">
                <Link to={LOGIN_ROUTES.platform}>Connexion administrateur</Link>
              </Button>
            </div>
          </div>
        </section>

        {/* Plateforme */}
        <section id="plateforme" className="scroll-mt-20 border-t border-border px-4 py-20 sm:px-6">
          <div className="mx-auto max-w-6xl">
            <SectionHeading eyebrow="Plateforme" title="Un seul outil pour tout le parcours de soin" />
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {FEATURES.map((feature) => (
                <Card key={feature.title} className="flex gap-4 p-5">
                  <IconTile icon={feature.icon} tone="accent2" />
                  <div>
                    <h3 className="font-heading text-sm font-semibold text-text">{feature.title}</h3>
                    <p className="mt-1 text-sm leading-relaxed text-text-muted">{feature.description}</p>
                  </div>
                </Card>
              ))}
            </div>
          </div>
        </section>

        {/* Sécurité */}
        <section id="securite" className="scroll-mt-20 px-4 py-20 sm:px-6">
          <div className="mx-auto max-w-6xl">
            <div className="glow-accent relative overflow-hidden rounded-2xl border border-border bg-surface px-6 py-12 sm:px-10">
              <div className="relative">
                <SectionHeading eyebrow="Sécurité" title="Sécurité & conformité" />
                <div className="grid gap-8 md:grid-cols-3">
                  {SECURITY_POINTS.map((point) => (
                    <div key={point.title}>
                      <IconTile icon={point.icon} />
                      <h3 className="mt-4 font-heading text-base font-semibold text-text">{point.title}</h3>
                      <p className="mt-1.5 text-sm leading-relaxed text-text-muted">{point.description}</p>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* Contact */}
        <section id="contact" className="scroll-mt-20 border-t border-border px-4 py-20 sm:px-6">
          <div className="mx-auto max-w-2xl text-center">
            <h2 className="font-heading text-2xl font-semibold text-text sm:text-3xl">
              Vous dirigez une structure de santé privée ?
            </h2>
            <p className="mt-3 text-sm text-text-muted sm:text-base">
              Découvrez comment Sanitaire peut centraliser la gestion de votre cabinet, laboratoire ou
              clinique. Échangeons sur vos besoins.
            </p>
            {CONTACT_EMAIL && (
              <Button asChild size="lg" className="mt-8">
                <a href={`mailto:${CONTACT_EMAIL}`}>Nous contacter</a>
              </Button>
            )}
          </div>
        </section>
      </main>

      <footer className="border-t border-border px-4 py-8 sm:px-6">
        <div className="mx-auto flex max-w-6xl flex-col items-center justify-between gap-4 sm:flex-row">
          <Logo />
          <p className="text-center text-xs text-text-subtle sm:text-right">
            © 2026 Sanitaire — Plateforme de gestion des structures sanitaires privées, Sénégal.
          </p>
        </div>
      </footer>
    </div>
  );
}
