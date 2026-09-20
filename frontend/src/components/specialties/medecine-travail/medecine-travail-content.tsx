import { useOpenConsultation } from "@/hooks/specialties/use-open-consultation";
import {
  useCreateOccupationalHealthRecord,
  useOccupationalHealthRecords,
} from "@/hooks/specialties/use-medecine-travail";
import { useSiteSelection } from "@/hooks/use-site-selection";
import { useSpecialtyAddForm } from "@/hooks/specialties/use-specialty-add-form";
import { SpecialtyDiagnosisCard } from "@/components/clinical/specialty-diagnosis-card";
import { SpecialtyHistorySection } from "@/components/clinical/specialty-history-section";
import { SiteSelectField } from "@/components/clinical/site-select-field";
import { Badge } from "@/components/ui/badge";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import { OCCUPATIONAL_HEALTH_RECORD_FIELDS } from "@/lib/specialty-configs/medecine-travail";
import { buildSpecialtyPayload } from "@/lib/specialty-validation";
import type { OccupationalFitnessStatus, OccupationalHealthRecord } from "@/types/specialty";

const FITNESS_LABELS: Record<OccupationalFitnessStatus, string> = {
  apte: "Apte",
  apte_avec_reserves: "Apte avec réserves",
  inapte: "Inapte",
};

const VISIT_TYPE_LABELS: Record<string, string> = {
  embauche: "Embauche",
  periodique: "Périodique",
  reprise: "Reprise",
  demande: "Demande",
};

export function MedecineTravailContent({ patientId }: { patientId: number }) {
  const siteSelection = useSiteSelection();
  const recordsQuery = useOccupationalHealthRecords(patientId);
  const openConsultationQuery = useOpenConsultation(patientId);
  const createMutation = useCreateOccupationalHealthRecord(patientId);
  const form = useSpecialtyAddForm(OCCUPATIONAL_HEALTH_RECORD_FIELDS);

  if (recordsQuery.isLoading) return <TableSkeleton rows={4} columns={2} />;
  if (recordsQuery.isError) {
    return <ErrorState message={apiErrorMessage(recordsQuery.error)} onRetry={() => recordsQuery.refetch()} />;
  }

  const siteId = siteSelection.siteId;
  const showSiteSelector = siteSelection.needsManualSelection;

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    // Cross-field rule not expressible in the generic per-field validator
    // (mirrors OccupationalHealthRecordRequest's required_if): a verdict of
    // "apte avec réserves" without a description of the reserves is
    // rejected server-side too — this is immediate client-side feedback.
    if (form.values.fitness_status === "apte_avec_reserves" && !form.values.restrictions?.trim()) {
      form.setGlobalError("Les restrictions sont obligatoires lorsque l'aptitude est « Apte avec réserves ».");
      return;
    }
    if (!siteId) {
      form.setGlobalError("Aucun site n'est associé à votre compte — impossible d'enregistrer la visite.");
      return;
    }
    createMutation.mutate(
      {
        ...buildSpecialtyPayload(OCCUPATIONAL_HEALTH_RECORD_FIELDS, form.values),
        site_id: siteId,
        consultation_id: openConsultationQuery.data?.id ?? null,
      },
      { onSuccess: form.reset, onError: (error) => form.setGlobalError(apiErrorMessage(error)) },
    );
  }

  const records = [...(recordsQuery.data ?? [])].sort((a, b) => b.visit_date.localeCompare(a.visit_date));

  return (
    <div className="space-y-4">
      <SpecialtyDiagnosisCard consultationId={openConsultationQuery.data?.id ?? null} />

      {showSiteSelector && (
        <SiteSelectField
          siteId={siteSelection.siteId}
          onChange={siteSelection.setSiteId}
          options={siteSelection.options}
          isLoading={siteSelection.isLoading}
        />
      )}

      <SpecialtyHistorySection
        title="Visites de médecine du travail"
        items={records}
        emptyLabel="Aucune visite enregistrée."
        addButtonLabel="Nouvelle visite"
        renderItem={(record) => <OccupationalRecordRow record={record} />}
        addForm={{
          fields: OCCUPATIONAL_HEALTH_RECORD_FIELDS,
          values: form.values,
          errors: form.errors,
          onChange: form.onChange,
          onSubmit: handleSubmit,
          isSubmitting: createMutation.isPending,
          globalError: form.globalError,
          submitLabel: "Enregistrer la visite",
        }}
      />
    </div>
  );
}

/** Bespoke due-date highlighting — the one genuinely specialty-specific piece of this screen. */
function nextVisitBadge(nextVisitDueAt: string | null) {
  if (!nextVisitDueAt) return null;
  const due = new Date(nextVisitDueAt);
  const today = new Date();
  const daysUntil = Math.floor((due.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

  if (daysUntil < 0) {
    return <Badge status="danger">Prochaine visite en retard ({formatDate(nextVisitDueAt)})</Badge>;
  }
  if (daysUntil <= 30) {
    return <Badge status="warning">Prochaine visite proche ({formatDate(nextVisitDueAt)})</Badge>;
  }
  return <Badge status="neutral">Prochaine visite : {formatDate(nextVisitDueAt)}</Badge>;
}

function OccupationalRecordRow({ record }: { record: OccupationalHealthRecord }) {
  return (
    <div className="space-y-2 rounded-md border border-border bg-surface px-4 py-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <p className="text-sm font-medium text-text">{formatDate(record.visit_date)}</p>
          <Badge status="neutral" dot={false}>
            {VISIT_TYPE_LABELS[record.visit_type] ?? record.visit_type}
          </Badge>
        </div>
        <Badge
          status={
            record.fitness_status === "apte"
              ? "success"
              : record.fitness_status === "apte_avec_reserves"
                ? "warning"
                : "danger"
          }
        >
          {FITNESS_LABELS[record.fitness_status]}
        </Badge>
      </div>

      {record.restrictions && <p className="text-xs text-text-muted">Restrictions : {record.restrictions}</p>}

      {record.risk_exposures && record.risk_exposures.length > 0 && (
        <div className="flex flex-wrap gap-1.5">
          {record.risk_exposures.map((exposure) => (
            <Badge key={exposure} status="neutral" dot={false}>
              {exposure}
            </Badge>
          ))}
        </div>
      )}

      {nextVisitBadge(record.next_visit_due_at)}
    </div>
  );
}
