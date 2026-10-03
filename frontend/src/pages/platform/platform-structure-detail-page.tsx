import {
  Activity,
  Archive,
  ArrowLeft,
  Building2,
  CalendarDays,
  CreditCard,
  Hash,
  Info,
  ListChecks,
  LoaderCircle,
  Mail,
  MapPin,
  Phone,
  Users,
  type LucideIcon,
} from "lucide-react";
import { useRef, useState, type KeyboardEvent, type ReactNode } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { PlatformInitials, PlatformNote } from "@/components/platform/platform-ui";
import { StructureAdministratorsCard } from "@/components/platform/structure-administrators-card";
import { structureTypeLabel } from "@/components/platform/structure-labels";
import { StructureStatusBadge } from "@/components/platform/structure-status-badge";
import { StructureSubscriptionsCard } from "@/components/platform/structure-subscriptions-card";
import { StructureActivityCard } from "@/components/platform/structure-activity-card";
import { StructureUsersCard } from "@/components/platform/structure-users-card";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Skeleton, TableSkeleton } from "@/components/ui/loading-state";
import { Switch } from "@/components/ui/switch";
import {
  useActivatePlatformStructure,
  useArchivePlatformStructure,
  useDeactivatePlatformStructure,
  usePlatformStructure,
} from "@/hooks/use-platform-structures";
import { usePlatformStructureModules, useUpdatePlatformStructureModule } from "@/hooks/use-platform-modules";
import { apiErrorMessage } from "@/lib/api-error";
import { cn } from "@/lib/utils";
import type { Structure, StructureModule } from "@/types/api";

/**
 * Livraison B : catalogue complet renvoyé par le backend (ModuleCatalog).
 * Un module du socle est toujours actif : interrupteur verrouillé, et le
 * backend refuse de toute façon sa désactivation (422).
 */
function ModuleRow({ structureId, module, readOnly }: {
  structureId: number;
  module: StructureModule;
  readOnly: boolean;
}) {
  const [error, setError] = useState<string | null>(null);
  const updateModule = useUpdatePlatformStructureModule(structureId);

  function handleToggle(next: boolean) {
    setError(null);
    updateModule.mutate(
      { module: module.module, isActive: next },
      { onError: (err) => setError(apiErrorMessage(err)) },
    );
  }

  return (
    <div className="flex items-center justify-between gap-4 border-b border-border px-5 py-3 transition-colors last:border-b-0 hover:bg-surface-hover/40">
      <div className="min-w-0">
        <p className="flex flex-wrap items-center gap-2 text-sm text-text">
          {module.label}
          {module.is_core && <Badge status="accent">Socle</Badge>}
        </p>
        {error && <p className="mt-1 text-xs text-danger">{error}</p>}
      </div>
      <div className="flex shrink-0 items-center gap-2">
        {updateModule.isPending && <LoaderCircle size={14} className="animate-spin text-text-muted" />}
        <Switch
          checked={module.is_active}
          onCheckedChange={handleToggle}
          disabled={module.is_core || readOnly || updateModule.isPending}
          label={`Module ${module.label}`}
        />
      </div>
    </div>
  );
}

function Fact({ icon: Icon, label, children }: { icon: LucideIcon; label: string; children: ReactNode }) {
  return (
    <div className="min-w-0">
      <dt className="flex items-center gap-1.5 text-xs text-text-subtle">
        <Icon size={13} className="shrink-0" />
        {label}
      </dt>
      <dd className="mt-1 break-words text-sm text-text">{children}</dd>
    </div>
  );
}

function HeaderSkeleton() {
  return (
    <div className="space-y-5">
      <div className="flex items-center gap-4">
        <Skeleton className="h-14 w-14 rounded-xl" />
        <div className="flex-1 space-y-2">
          <Skeleton className="h-5 w-56 max-w-full" />
          <Skeleton className="h-3.5 w-40 max-w-full" />
        </div>
      </div>
      <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
        {Array.from({ length: 6 }).map((_, i) => (
          <div key={i} className="space-y-2">
            <Skeleton className="h-3 w-16" />
            <Skeleton className="h-4 w-24 max-w-full" />
          </div>
        ))}
      </div>
    </div>
  );
}

type TabKey = "abonnement" | "acces" | "activite";

const TABS: { key: TabKey; label: string; icon: LucideIcon }[] = [
  { key: "abonnement", label: "Abonnement & modules", icon: CreditCard },
  { key: "acces", label: "Administrateurs & utilisateurs", icon: Users },
  { key: "activite", label: "Activité", icon: Activity },
];

/** Onglets accessibles (flèches gauche/droite). Les panneaux restent montés : seul l'affichage change. */
function DetailTabs({ active, onChange }: { active: TabKey; onChange: (key: TabKey) => void }) {
  const refs = useRef<Record<TabKey, HTMLButtonElement | null>>({ abonnement: null, acces: null, activite: null });

  function handleKeyDown(event: KeyboardEvent<HTMLDivElement>) {
    if (event.key !== "ArrowRight" && event.key !== "ArrowLeft") return;
    event.preventDefault();
    const index = TABS.findIndex((tab) => tab.key === active);
    const next = TABS[(index + (event.key === "ArrowRight" ? 1 : TABS.length - 1)) % TABS.length];
    onChange(next.key);
    refs.current[next.key]?.focus();
  }

  return (
    <div
      role="tablist"
      aria-label="Sections de la fiche structure"
      onKeyDown={handleKeyDown}
      className="flex gap-1 overflow-x-auto border-b border-border [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
    >
      {TABS.map((tab) => {
        const selected = tab.key === active;
        return (
          <button
            key={tab.key}
            ref={(el) => {
              refs.current[tab.key] = el;
            }}
            type="button"
            role="tab"
            id={`structure-tab-${tab.key}`}
            aria-selected={selected}
            aria-controls={`structure-panel-${tab.key}`}
            tabIndex={selected ? 0 : -1}
            onClick={() => onChange(tab.key)}
            className={cn(
              "-mb-px flex items-center gap-2 whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent",
              selected
                ? "border-accent text-text"
                : "border-transparent text-text-muted hover:border-border-strong hover:text-text",
            )}
          >
            <tab.icon size={15} className={selected ? "text-accent-light" : undefined} />
            {tab.label}
          </button>
        );
      })}
    </div>
  );
}

function TabPanel({ tab, active, children }: { tab: TabKey; active: TabKey; children: ReactNode }) {
  return (
    <div
      role="tabpanel"
      id={`structure-panel-${tab}`}
      aria-labelledby={`structure-tab-${tab}`}
      hidden={tab !== active}
      className="min-w-0"
    >
      {children}
    </div>
  );
}

function formatDay(value: string): string {
  return new Date(value).toLocaleDateString("fr-FR");
}

export function PlatformStructureDetailPage() {
  const { id } = useParams<{ id: string }>();
  const structureId = id ? Number(id) : undefined;
  const navigate = useNavigate();

  const structureQuery = usePlatformStructure(structureId);
  const modulesQuery = usePlatformStructureModules(structureId);
  const activateStructure = useActivatePlatformStructure();
  const deactivateStructure = useDeactivatePlatformStructure();
  const archiveStructure = useArchivePlatformStructure();
  const [statusError, setStatusError] = useState<string | null>(null);
  const [confirmArchive, setConfirmArchive] = useState(false);
  const [tab, setTab] = useState<TabKey>("abonnement");
  const isArchived = Boolean(structureQuery.data?.archived_at);
  const toggling = activateStructure.isPending || deactivateStructure.isPending;

  const activeModules = modulesQuery.data?.filter((m) => m.is_active).length ?? 0;

  function handleArchive() {
    if (!structureQuery.data) return;
    setStatusError(null);
    archiveStructure.mutate(structureQuery.data.id, {
      onSuccess: () => setConfirmArchive(false),
      onError: (err) => {
        setConfirmArchive(false);
        setStatusError(apiErrorMessage(err));
      },
    });
  }

  function handleToggleActive() {
    if (!structureQuery.data) return;
    setStatusError(null);
    const mutation = structureQuery.data.is_active ? deactivateStructure : activateStructure;
    mutation.mutate(structureQuery.data.id, {
      onError: (err) => setStatusError(apiErrorMessage(err)),
    });
  }

  function renderIdentity(structure: Structure) {
    return (
      <div className="space-y-5">
        <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
          <div className="flex min-w-0 items-start gap-4">
            <PlatformInitials name={structure.legal_name} className="h-14 w-14 rounded-xl text-base" />
            <div className="min-w-0">
              <div className="flex flex-wrap items-center gap-2">
                <h1 className="break-words font-heading text-xl font-semibold text-text sm:text-2xl">
                  {structure.legal_name}
                </h1>
                <StructureStatusBadge structure={structure} />
              </div>
              <p className="mt-1 text-sm text-text-muted">
                {structure.code} · {structureTypeLabel(structure.type)}
                {structure.city && <> · {structure.city}</>}
              </p>
            </div>
          </div>

          {!isArchived && (
            <div className="flex flex-wrap items-center gap-2 md:shrink-0 md:justify-end">
              <Button size="sm" variant="ghost" onClick={() => setConfirmArchive(true)} disabled={archiveStructure.isPending}>
                <Archive size={14} />
                Archiver définitivement
              </Button>
              <Button
                size="sm"
                variant={structure.is_active ? "danger" : "secondary"}
                onClick={handleToggleActive}
                disabled={toggling}
              >
                {toggling && <LoaderCircle size={14} className="animate-spin" />}
                {structure.is_active ? "Suspendre la structure" : "Réactiver la structure"}
              </Button>
            </div>
          )}
        </div>

        <dl className="grid grid-cols-2 gap-x-4 gap-y-4 border-t border-border pt-5 sm:grid-cols-3 lg:grid-cols-6">
          <Fact icon={Hash} label="Code">
            <span className="font-mono text-xs">{structure.code}</span>
          </Fact>
          <Fact icon={Building2} label="Type">
            {structureTypeLabel(structure.type)}
          </Fact>
          <Fact icon={MapPin} label="Ville">
            {structure.city ?? "—"}
          </Fact>
          <Fact icon={Phone} label="Téléphone">
            {structure.phone ?? "—"}
          </Fact>
          <Fact icon={Mail} label="E-mail">
            {structure.email ?? "—"}
          </Fact>
          <Fact icon={CalendarDays} label="Créée le">
            {structure.created_at ? formatDay(structure.created_at) : "—"}
          </Fact>
        </dl>

        {isArchived && (
          <p className="flex items-start gap-2 rounded-md border border-danger/30 bg-danger/5 px-3 py-2.5 text-xs text-text-muted">
            <Archive size={14} className="mt-0.5 shrink-0 text-danger" />
            <span>
              Archivée le {new Date(structure.archived_at as string).toLocaleDateString("fr-FR")}. Aucun compte de cette
              structure ne peut plus se connecter. La fiche et son historique restent consultables, en lecture seule.
            </span>
          </p>
        )}
        {statusError && (
          <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{statusError}</p>
        )}
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <Button variant="ghost" size="sm" className="-ml-2" onClick={() => navigate("/platform/structures")}>
        <ArrowLeft size={14} />
        Retour aux structures
      </Button>

      <Card className="glow-accent overflow-hidden p-5 sm:p-6">
        <div className="relative">
          {structureQuery.isLoading ? (
            <HeaderSkeleton />
          ) : structureQuery.isError ? (
            <ErrorState message={apiErrorMessage(structureQuery.error)} onRetry={() => structureQuery.refetch()} />
          ) : structureQuery.data ? (
            renderIdentity(structureQuery.data)
          ) : null}
        </div>
      </Card>

      <div className="space-y-6">
        <DetailTabs active={tab} onChange={setTab} />

        <TabPanel tab="abonnement" active={tab}>
          <div className="grid grid-cols-1 items-start gap-6 xl:grid-cols-5">
            <div className="min-w-0 xl:col-span-3">
              {structureId && <StructureSubscriptionsCard structureId={structureId} readOnly={isArchived} />}
            </div>

            <Card className="min-w-0 xl:col-span-2">
              <CardHeader>
                <CardTitle>Modules</CardTitle>
                {modulesQuery.data && modulesQuery.data.length > 0 && (
                  <Badge status="accent" dot={false}>
                    {activeModules} / {modulesQuery.data.length} actifs
                  </Badge>
                )}
              </CardHeader>
              <CardContent className="p-0">
                {modulesQuery.isLoading ? (
                  <div className="p-5">
                    <TableSkeleton columns={2} />
                  </div>
                ) : modulesQuery.isError ? (
                  <div className="p-5">
                    <ErrorState message={apiErrorMessage(modulesQuery.error)} onRetry={() => modulesQuery.refetch()} />
                  </div>
                ) : !modulesQuery.data || modulesQuery.data.length === 0 ? (
                  <EmptyState icon={ListChecks} title="Aucun module" className="m-4 py-10" />
                ) : structureId ? (
                  <div className="border-t border-border">
                    {modulesQuery.data.map((module) => (
                      <ModuleRow key={module.module} structureId={structureId} module={module} readOnly={isArchived} />
                    ))}
                  </div>
                ) : null}
                <PlatformNote icon={Info} className="border-t border-border px-5 py-3">
                  Désactiver un module premium masque ses menus et bloque ses routes pour toute la structure, sans
                  supprimer aucune donnée : la réactivation rend l'accès à l'identique.
                </PlatformNote>
              </CardContent>
            </Card>
          </div>
        </TabPanel>

        <TabPanel tab="acces" active={tab}>
          <div className="space-y-6">
            {structureId && <StructureAdministratorsCard structureId={structureId} readOnly={isArchived} />}
            {structureId && <StructureUsersCard structureId={structureId} />}
          </div>
        </TabPanel>

        <TabPanel tab="activite" active={tab}>
          {structureId && <StructureActivityCard structureId={structureId} />}
        </TabPanel>
      </div>

      <ConfirmDialog
        open={confirmArchive}
        onOpenChange={setConfirmArchive}
        title="Archiver cette structure ?"
        description="Tous ses comptes (personnel, patients, prescripteurs) perdent immédiatement l'accès, y compris les sessions ouvertes. Aucune donnée n'est supprimée et la fiche reste consultable ici, mais l'archivage ne peut pas être annulé depuis cette interface."
        confirmLabel="Archiver"
        isPending={archiveStructure.isPending}
        onConfirm={handleArchive}
      />
    </div>
  );
}
