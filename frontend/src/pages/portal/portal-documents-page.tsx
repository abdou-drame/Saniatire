import { FileText, TestTubes } from "lucide-react";
import { Card, CardContent } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { usePortalDocuments } from "@/hooks/portal/use-portal-documents";
import { formatDate } from "@/lib/datetime";
import { portalErrorMessage } from "@/lib/portal-error";
import type { ImagingReportDocument, LabResultDocument } from "@/types/api";

function LabResultCard({ doc }: { doc: LabResultDocument }) {
  return (
    <div className="rounded-md border border-border bg-surface px-4 py-3">
      <div className="flex items-center justify-between gap-3">
        <p className="text-sm font-medium text-text">{doc.type ?? "Résultat de laboratoire"}</p>
        {doc.date && <p className="text-xs text-text-subtle">{formatDate(doc.date)}</p>}
      </div>
      {doc.praticien && <p className="mt-0.5 text-xs text-text-muted">{doc.praticien}</p>}
      {(doc.value || doc.interpretation) && (
        <p className="mt-2 text-sm text-text">
          {doc.value}
          {doc.unit ? ` ${doc.unit}` : ""}
          {doc.interpretation ? ` — ${doc.interpretation}` : ""}
        </p>
      )}
      {(doc.reference_min || doc.reference_max) && (
        <p className="mt-1 text-xs text-text-subtle">
          Valeurs de référence : {doc.reference_min ?? "—"} à {doc.reference_max ?? "—"}
        </p>
      )}
    </div>
  );
}

function ImagingReportCard({ doc }: { doc: ImagingReportDocument }) {
  return (
    <div className="rounded-md border border-border bg-surface px-4 py-3">
      <div className="flex items-center justify-between gap-3">
        <p className="text-sm font-medium text-text">{doc.type ?? "Compte rendu d'imagerie"}</p>
        {doc.date && <p className="text-xs text-text-subtle">{formatDate(doc.date)}</p>}
      </div>
      {doc.praticien && <p className="mt-0.5 text-xs text-text-muted">{doc.praticien}</p>}
      {doc.content && <p className="mt-2 whitespace-pre-line text-sm text-text">{doc.content}</p>}
    </div>
  );
}

export function PortalDocumentsPage() {
  const documentsQuery = usePortalDocuments();

  return (
    <div className="space-y-6">
      <h2 className="font-heading text-lg font-semibold text-text">Mes documents</h2>

      {documentsQuery.isError ? (
        <ErrorState message={portalErrorMessage(documentsQuery.error)} onRetry={() => documentsQuery.refetch()} />
      ) : documentsQuery.isLoading ? (
        <TableSkeleton rows={3} columns={2} />
      ) : (
        <>
          <Card>
            <CardContent className="space-y-3 pt-5">
              <h3 className="text-sm font-medium text-text">Résultats de laboratoire</h3>
              {documentsQuery.data!.resultats_laboratoire.length === 0 ? (
                <EmptyState icon={TestTubes} title="Aucun résultat disponible" description="Vos résultats de laboratoire transmis apparaîtront ici." />
              ) : (
                <div className="space-y-2">
                  {documentsQuery.data!.resultats_laboratoire.map((doc) => (
                    <LabResultCard key={doc.id} doc={doc} />
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardContent className="space-y-3 pt-5">
              <h3 className="text-sm font-medium text-text">Comptes rendus d'imagerie</h3>
              {documentsQuery.data!.comptes_rendus_imagerie.length === 0 ? (
                <EmptyState icon={FileText} title="Aucun compte rendu disponible" description="Vos comptes rendus d'imagerie transmis apparaîtront ici." />
              ) : (
                <div className="space-y-2">
                  {documentsQuery.data!.comptes_rendus_imagerie.map((doc) => (
                    <ImagingReportCard key={doc.id} doc={doc} />
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </>
      )}
    </div>
  );
}
