import { ChevronLeft, ChevronRight, ClipboardList, ShieldCheck } from "lucide-react";
import { useEffect, useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { usePlatformAuditLogs, type PlatformAuditLogFilters } from "@/hooks/use-platform-audit-logs";
import { usePlatformStructures } from "@/hooks/use-platform-structures";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import type { PlatformAuditLogEntry } from "@/types/api";

/** Dernier segment de subject_type (nom de classe FQCN) + #id, même patron que AuditPage. */
function subjectLabel(subjectType: string | null, subjectId: number | null): string {
  if (!subjectType) return "—";
  const shortName = subjectType.split("\\").pop() || subjectType;
  return subjectId !== null ? `${shortName} #${subjectId}` : shortName;
}

/**
 * Consultation en lecture seule du journal d'audit propre à l'administration
 * plateforme — distinct du journal /audit (voir AuditLogController) : ces
 * entrées, portant le structure_id de la structure concernée et non celui
 * d'un acteur de structure, n'y apparaissent jamais (log_name dédié
 * 'administration_plateforme', exclu explicitement côté backend).
 */
export function PlatformAuditPage() {
  const structuresQuery = usePlatformStructures();

  const [structureFilter, setStructureFilter] = useState("");
  const [fromFilter, setFromFilter] = useState("");
  const [toFilter, setToFilter] = useState("");
  const [pageNumber, setPageNumber] = useState(1);
  const [selected, setSelected] = useState<PlatformAuditLogEntry | null>(null);

  useEffect(() => {
    setPageNumber(1);
  }, [structureFilter, fromFilter, toFilter]);

  const filters: PlatformAuditLogFilters = {
    structure_id: structureFilter ? Number(structureFilter) : undefined,
    from: fromFilter || undefined,
    to: toFilter || undefined,
    page: pageNumber,
  };

  const auditQuery = usePlatformAuditLogs(filters);
  const page = auditQuery.data;

  function resetFilters() {
    setStructureFilter("");
    setFromFilter("");
    setToFilter("");
    setPageNumber(1);
  }

  const columns: DataTableColumn<PlatformAuditLogEntry>[] = [
    { key: "created_at", header: "Date", render: (row) => formatDateTime(row.created_at) },
    {
      key: "structure",
      header: "Structure concernée",
      render: (row) => {
        if (row.structure_id === null) return "—";
        const structure = structuresQuery.data?.find((s) => s.id === row.structure_id);
        return structure ? structure.legal_name : `Structure #${row.structure_id}`;
      },
    },
    { key: "description", header: "Action", render: (row) => row.description ?? "—" },
    { key: "subject", header: "Concerne", render: (row) => subjectLabel(row.subject_type, row.subject_id) },
    {
      key: "details",
      header: "Détails",
      render: (row) => (
        <Button variant="secondary" size="sm" onClick={() => setSelected(row)}>
          Voir
        </Button>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-heading text-xl font-semibold text-text">Journal d'audit plateforme</h1>
        <p className="mt-1 text-sm text-text-muted">
          Actions de l'administrateur de plateforme, hors du cadre normal d'isolation par structure.
        </p>
      </div>

      <div className="flex items-start gap-3 rounded-lg border border-border bg-surface-hover/40 px-4 py-3">
        <ShieldCheck size={20} className="mt-0.5 shrink-0 text-text-muted" />
        <p className="text-sm text-text-muted">
          Journal en lecture seule — infalsifiable : toute tentative de modification ou de suppression d'une entrée
          est rejetée côté serveur.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Filtres</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div>
              <Label>Structure</Label>
              <Select value={structureFilter} onChange={(e) => setStructureFilter(e.target.value)}>
                <option value="">Toutes les structures</option>
                {(structuresQuery.data ?? []).map((s) => (
                  <option key={s.id} value={s.id}>
                    {s.legal_name}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <Label>Du</Label>
              <Input type="date" value={fromFilter} onChange={(e) => setFromFilter(e.target.value)} />
            </div>
            <div>
              <Label>Au</Label>
              <Input type="date" value={toFilter} onChange={(e) => setToFilter(e.target.value)} />
            </div>
          </div>
          <Button variant="secondary" size="sm" onClick={resetFilters}>
            Réinitialiser les filtres
          </Button>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Journal</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {auditQuery.isError ? (
            <ErrorState message={apiErrorMessage(auditQuery.error)} onRetry={() => auditQuery.refetch()} />
          ) : (
            <>
              <DataTable
                columns={columns}
                data={page?.data ?? []}
                rowKey={(row) => row.id}
                isLoading={auditQuery.isLoading}
                pageSize={Math.max(page?.data.length ?? 1, 1)}
                emptyState={
                  <EmptyState
                    icon={ClipboardList}
                    title="Aucune entrée"
                    description="Aucune action du journal ne correspond à ces filtres."
                  />
                }
              />

              {page && page.total > 0 && (
                <div className="flex items-center justify-between border-t border-border pt-3 text-xs text-text-muted">
                  <span>
                    Page {page.current_page} / {page.last_page} — {page.total} résultats
                  </span>
                  <div className="flex items-center gap-1">
                    <button
                      className="rounded p-1 hover:bg-surface-hover disabled:pointer-events-none disabled:opacity-40"
                      disabled={page.current_page <= 1}
                      onClick={() => setPageNumber((p) => Math.max(1, p - 1))}
                      aria-label="Page précédente"
                    >
                      <ChevronLeft size={16} />
                    </button>
                    <button
                      className="rounded p-1 hover:bg-surface-hover disabled:pointer-events-none disabled:opacity-40"
                      disabled={page.current_page >= page.last_page}
                      onClick={() => setPageNumber((p) => Math.min(page.last_page, p + 1))}
                      aria-label="Page suivante"
                    >
                      <ChevronRight size={16} />
                    </button>
                  </div>
                </div>
              )}
            </>
          )}
        </CardContent>
      </Card>

      <Dialog open={selected !== null} onOpenChange={(open) => !open && setSelected(null)}>
        <DialogContent className="max-w-2xl">
          {selected && (
            <>
              <DialogHeader>
                <DialogTitle>Détails de l'entrée #{selected.id}</DialogTitle>
              </DialogHeader>
              <pre className="mt-1 max-h-80 overflow-auto rounded-md bg-surface-hover p-3 text-xs text-text">
                {JSON.stringify(selected.properties ?? {}, null, 2)}
              </pre>
            </>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}
