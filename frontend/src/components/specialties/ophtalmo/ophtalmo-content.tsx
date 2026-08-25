import { useAuth } from "@/hooks/use-auth";
import { useOpenConsultation } from "@/hooks/specialties/use-open-consultation";
import { useCreateOphtalmoRecord, useOphtalmoRecords } from "@/hooks/specialties/use-ophtalmo";
import { useSpecialtyAddForm } from "@/hooks/specialties/use-specialty-add-form";
import { SpecialtyDiagnosisCard } from "@/components/clinical/specialty-diagnosis-card";
import { SpecialtyHistorySection } from "@/components/clinical/specialty-history-section";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import { OPHTALMO_RECORD_FIELDS } from "@/lib/specialty-configs/ophtalmo";
import { buildSpecialtyPayload } from "@/lib/specialty-validation";
import type { OphtalmoRecord } from "@/types/specialty";

export function OphtalmoContent({ patientId }: { patientId: number }) {
  const { user } = useAuth();
  const recordsQuery = useOphtalmoRecords(patientId);
  const openConsultationQuery = useOpenConsultation(patientId);
  const createMutation = useCreateOphtalmoRecord(patientId);
  const form = useSpecialtyAddForm(OPHTALMO_RECORD_FIELDS);

  if (recordsQuery.isLoading) return <TableSkeleton rows={4} columns={2} />;
  if (recordsQuery.isError) {
    return <ErrorState message={apiErrorMessage(recordsQuery.error)} onRetry={() => recordsQuery.refetch()} />;
  }

  const siteId = user?.sites[0]?.id ?? null;

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    if (!siteId) {
      form.setGlobalError("Aucun site n'est associé à votre compte — impossible d'enregistrer l'examen.");
      return;
    }
    createMutation.mutate(
      {
        ...buildSpecialtyPayload(OPHTALMO_RECORD_FIELDS, form.values),
        site_id: siteId,
        consultation_id: openConsultationQuery.data?.id ?? null,
      },
      { onSuccess: form.reset, onError: (error) => form.setGlobalError(apiErrorMessage(error)) },
    );
  }

  const records = [...(recordsQuery.data ?? [])].sort((a, b) => b.examined_at.localeCompare(a.examined_at));

  return (
    <div className="space-y-4">
      <SpecialtyDiagnosisCard consultationId={openConsultationQuery.data?.id ?? null} />

      <SpecialtyHistorySection
        title="Examens ophtalmologiques"
        items={records}
        emptyLabel="Aucun examen enregistré."
        addButtonLabel="Nouvel examen"
        renderItem={(record) => <OphtalmoRecordRow record={record} />}
        addForm={{
          fields: OPHTALMO_RECORD_FIELDS,
          values: form.values,
          errors: form.errors,
          onChange: form.onChange,
          onSubmit: handleSubmit,
          isSubmitting: createMutation.isPending,
          globalError: form.globalError,
          submitLabel: "Enregistrer l'examen",
        }}
      />
    </div>
  );
}

function OphtalmoRecordRow({ record }: { record: OphtalmoRecord }) {
  return (
    <div className="space-y-1.5 rounded-md border border-border bg-surface px-4 py-3">
      <p className="text-sm font-medium text-text">{formatDate(record.examined_at)}</p>
      <div className="grid grid-cols-1 gap-x-6 gap-y-1 text-xs text-text-muted sm:grid-cols-2">
        <p>
          <span className="text-text-subtle">AV OD : </span>
          {record.visual_acuity_od_corrected ?? record.visual_acuity_od_uncorrected ?? "—"}
          {" · "}
          <span className="text-text-subtle">PIO : </span>
          {record.intraocular_pressure_od ?? "—"} mmHg
        </p>
        <p>
          <span className="text-text-subtle">AV OG : </span>
          {record.visual_acuity_og_corrected ?? record.visual_acuity_og_uncorrected ?? "—"}
          {" · "}
          <span className="text-text-subtle">PIO : </span>
          {record.intraocular_pressure_og ?? "—"} mmHg
        </p>
      </div>
      {record.fundus_exam && <p className="text-xs text-text-muted">Fond d'œil : {record.fundus_exam}</p>}
      {record.optical_correction_prescription && (
        <p className="text-xs text-text-muted">Prescription : {record.optical_correction_prescription}</p>
      )}
    </div>
  );
}
