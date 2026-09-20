import { ArrowLeft, ListChecks, LoaderCircle } from "lucide-react";
import { useState } from "react";
import { useNavigate, useParams } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Skeleton, TableSkeleton } from "@/components/ui/loading-state";
import { Switch } from "@/components/ui/switch";
import {
  useActivatePlatformStructure,
  useDeactivatePlatformStructure,
  usePlatformStructure,
} from "@/hooks/use-platform-structures";
import { usePlatformStructureModules, useUpdatePlatformStructureModule } from "@/hooks/use-platform-modules";
import { apiErrorMessage } from "@/lib/api-error";

const STRUCTURE_TYPE_LABEL: Record<string, string> = {
  cabinet: "Cabinet",
  centre_specialise: "Centre spécialisé",
  laboratoire: "Laboratoire",
  imagerie: "Imagerie",
  clinique: "Clinique",
  polyclinique: "Polyclinique",
  groupe_sante: "Groupe santé",
};

function ModuleRow({ structureId, moduleId, moduleName, isActive }: {
  structureId: number;
  moduleId: number;
  moduleName: string;
  isActive: boolean;
}) {
  const [error, setError] = useState<string | null>(null);
  const updateModule = useUpdatePlatformStructureModule(structureId);

  function handleToggle(next: boolean) {
    setError(null);
    updateModule.mutate(
      { moduleId, isActive: next },
      { onError: (err) => setError(apiErrorMessage(err)) },
    );
  }

  return (
    <div className="flex items-center justify-between gap-4 border-b border-border px-4 py-3 last:border-b-0">
      <div>
        <p className="text-sm text-text">{moduleName}</p>
        {error && <p className="mt-1 text-xs text-danger">{error}</p>}
      </div>
      <div className="flex items-center gap-2">
        {updateModule.isPending && <LoaderCircle size={14} className="animate-spin text-text-muted" />}
        <Switch
          checked={isActive}
          onCheckedChange={handleToggle}
          disabled={updateModule.isPending}
          label={`Module ${moduleName}`}
        />
      </div>
    </div>
  );
}

export function PlatformStructureDetailPage() {
  const { id } = useParams<{ id: string }>();
  const structureId = id ? Number(id) : undefined;
  const navigate = useNavigate();

  const structureQuery = usePlatformStructure(structureId);
  const modulesQuery = usePlatformStructureModules(structureId);
  const activateStructure = useActivatePlatformStructure();
  const deactivateStructure = useDeactivatePlatformStructure();
  const [statusError, setStatusError] = useState<string | null>(null);

  function handleToggleActive() {
    if (!structureQuery.data) return;
    setStatusError(null);
    const mutation = structureQuery.data.is_active ? deactivateStructure : activateStructure;
    mutation.mutate(structureQuery.data.id, {
      onError: (err) => setStatusError(apiErrorMessage(err)),
    });
  }

  return (
    <div className="space-y-6">
      <Button variant="ghost" size="sm" onClick={() => navigate("/platform/structures")}>
        <ArrowLeft size={14} />
        Retour aux structures
      </Button>

      <Card>
        <CardHeader>
          <CardTitle>Structure</CardTitle>
        </CardHeader>
        <CardContent>
          {structureQuery.isLoading ? (
            <Skeleton className="h-24 w-full" />
          ) : structureQuery.isError ? (
            <ErrorState message={apiErrorMessage(structureQuery.error)} onRetry={() => structureQuery.refetch()} />
          ) : structureQuery.data ? (
            <div className="space-y-3">
              <div className="flex items-center justify-between gap-3">
                <div>
                  <p className="text-base font-medium text-text">{structureQuery.data.legal_name}</p>
                  <p className="text-xs text-text-muted">
                    {structureQuery.data.code} · {STRUCTURE_TYPE_LABEL[structureQuery.data.type] ?? structureQuery.data.type}
                    {structureQuery.data.city && <> · {structureQuery.data.city}</>}
                  </p>
                </div>
                <Badge status={structureQuery.data.is_active ? "success" : "neutral"}>
                  {structureQuery.data.is_active ? "Active" : "Inactive"}
                </Badge>
              </div>

              <div className="flex items-center gap-2">
                <Button
                  size="sm"
                  variant={structureQuery.data.is_active ? "danger" : "secondary"}
                  onClick={handleToggleActive}
                  disabled={activateStructure.isPending || deactivateStructure.isPending}
                >
                  {(activateStructure.isPending || deactivateStructure.isPending) && (
                    <LoaderCircle size={14} className="animate-spin" />
                  )}
                  {structureQuery.data.is_active ? "Désactiver la structure" : "Activer la structure"}
                </Button>
              </div>
              {statusError && (
                <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
                  {statusError}
                </p>
              )}
            </div>
          ) : null}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Modules</CardTitle>
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
            <EmptyState icon={ListChecks} title="Aucun module" className="py-14" />
          ) : structureId ? (
            <div>
              {modulesQuery.data.map((module) => (
                <ModuleRow
                  key={module.id}
                  structureId={structureId}
                  moduleId={module.id}
                  moduleName={module.module}
                  isActive={module.is_active}
                />
              ))}
            </div>
          ) : null}
        </CardContent>
      </Card>
      <p className="text-xs text-text-subtle">
        Aucune autre partie de l'application n'est branchée sur ces bascules à ce stade — c'est normal.
      </p>
    </div>
  );
}
