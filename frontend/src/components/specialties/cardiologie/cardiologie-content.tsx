import { LoaderCircle } from "lucide-react";
import { SpecialtyDiagnosisCard } from "@/components/clinical/specialty-diagnosis-card";
import { SpecialtyForm } from "@/components/clinical/specialty-form";
import { SpecialtyHistorySection } from "@/components/clinical/specialty-history-section";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { useAuth } from "@/hooks/use-auth";
import { useOpenConsultation } from "@/hooks/specialties/use-open-consultation";
import {
  useAddCardioEcgResult,
  useAddCardioReading,
  useCardioRecord,
  useCreateCardioRecord,
} from "@/hooks/specialties/use-cardiologie";
import { useSpecialtyAddForm } from "@/hooks/specialties/use-specialty-add-form";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate, formatDateTime } from "@/lib/datetime";
import {
  CARDIO_ECG_RESULT_FIELDS,
  CARDIO_READING_FIELDS,
  CARDIO_RECORD_FIELDS,
} from "@/lib/specialty-configs/cardiologie";
import { buildSpecialtyPayload } from "@/lib/specialty-validation";
import type { CardioEcgResult, CardioReading } from "@/types/specialty";

export function CardiologieContent({ patientId }: { patientId: number }) {
  const { user } = useAuth();
  const recordQuery = useCardioRecord(patientId);
  const openConsultationQuery = useOpenConsultation(patientId);
  const createMutation = useCreateCardioRecord(patientId);
  const form = useSpecialtyAddForm(CARDIO_RECORD_FIELDS);

  if (recordQuery.isLoading) return <TableSkeleton rows={4} columns={2} />;
  if (recordQuery.isError) {
    return <ErrorState message={apiErrorMessage(recordQuery.error)} onRetry={() => recordQuery.refetch()} />;
  }

  if (!recordQuery.data) {
    const siteId = user?.sites[0]?.id ?? null;

    function handleCreate() {
      form.setGlobalError(null);
      if (!form.validate()) return;
      if (!siteId) {
        form.setGlobalError("Aucun site n'est associé à votre compte — impossible de créer le dossier.");
        return;
      }
      createMutation.mutate(
        {
          ...buildSpecialtyPayload(CARDIO_RECORD_FIELDS, form.values),
          site_id: siteId,
          consultation_id: openConsultationQuery.data?.id ?? null,
        },
        { onError: (error) => form.setGlobalError(apiErrorMessage(error)) },
      );
    }

    return (
      <Card>
        <CardHeader>
          <CardTitle>Créer le dossier cardiologique</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <SpecialtyForm
            columns={2}
            fields={CARDIO_RECORD_FIELDS}
            values={form.values}
            errors={form.errors}
            disabled={createMutation.isPending}
            onChange={form.onChange}
          />
          {form.globalError && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
              {form.globalError}
            </p>
          )}
          <Button onClick={handleCreate} disabled={createMutation.isPending}>
            {createMutation.isPending && <LoaderCircle size={16} className="animate-spin" />}
            Créer le dossier
          </Button>
        </CardContent>
      </Card>
    );
  }

  const record = recordQuery.data;

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>Dossier cardiologique</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <div className="flex flex-wrap gap-6 text-sm text-text">
            <p>
              <span className="text-text-subtle">Examen initial : </span>
              {formatDate(record.examined_at)}
            </p>
          </div>
          {record.risk_factors && record.risk_factors.length > 0 && (
            <div className="flex flex-wrap gap-1.5">
              {record.risk_factors.map((factor) => (
                <Badge key={factor} status="warning" dot={false}>
                  {factor}
                </Badge>
              ))}
            </div>
          )}
          {record.current_treatment && (
            <p className="text-xs text-text-muted">Traitement en cours : {record.current_treatment}</p>
          )}
        </CardContent>
      </Card>

      <SpecialtyDiagnosisCard consultationId={record.consultation_id} />

      <ReadingsSection patientId={patientId} recordId={record.id} readings={record.readings} />
      <EcgResultsSection patientId={patientId} recordId={record.id} ecgResults={record.ecg_results} />
    </div>
  );
}

function ReadingsSection({
  patientId,
  recordId,
  readings,
}: {
  patientId: number;
  recordId: number;
  readings: CardioReading[];
}) {
  const form = useSpecialtyAddForm(CARDIO_READING_FIELDS);
  const addMutation = useAddCardioReading(patientId, recordId);

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    addMutation.mutate(buildSpecialtyPayload(CARDIO_READING_FIELDS, form.values), {
      onSuccess: form.reset,
      onError: (error) => form.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title="Mesures (TA / FC)"
      items={[...readings].sort((a, b) => b.measured_at.localeCompare(a.measured_at))}
      emptyLabel="Aucune mesure enregistrée."
      addButtonLabel="Ajouter une mesure"
      renderItem={(reading) => (
        <div className="rounded-md border border-border bg-surface px-4 py-3 text-sm text-text">
          <p className="font-medium">{formatDateTime(reading.measured_at)}</p>
          <p className="text-xs text-text-muted">
            TA {reading.blood_pressure_systolic}/{reading.blood_pressure_diastolic} mmHg · FC {reading.heart_rate} bpm
            {reading.rhythm && ` · rythme ${reading.rhythm === "regulier" ? "régulier" : "irrégulier"}`}
          </p>
        </div>
      )}
      addForm={{
        fields: CARDIO_READING_FIELDS,
        values: form.values,
        errors: form.errors,
        onChange: form.onChange,
        onSubmit: handleSubmit,
        isSubmitting: addMutation.isPending,
        globalError: form.globalError,
        submitLabel: "Enregistrer la mesure",
      }}
    />
  );
}

function EcgResultsSection({
  patientId,
  recordId,
  ecgResults,
}: {
  patientId: number;
  recordId: number;
  ecgResults: CardioEcgResult[];
}) {
  const form = useSpecialtyAddForm(CARDIO_ECG_RESULT_FIELDS);
  const addMutation = useAddCardioEcgResult(patientId, recordId);

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    addMutation.mutate(buildSpecialtyPayload(CARDIO_ECG_RESULT_FIELDS, form.values), {
      onSuccess: form.reset,
      onError: (error) => form.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title="Résultats ECG"
      items={[...ecgResults].sort((a, b) => b.performed_at.localeCompare(a.performed_at))}
      emptyLabel="Aucun ECG enregistré."
      addButtonLabel="Ajouter un ECG"
      renderItem={(ecg) => (
        <div className="rounded-md border border-border bg-surface px-4 py-3 text-sm text-text">
          <p className="font-medium">{formatDateTime(ecg.performed_at)}</p>
          <p className="text-xs text-text-muted">
            Rythme : {ecg.rhythm}
            {ecg.heart_rate && ` · FC ${ecg.heart_rate} bpm`}
          </p>
          {ecg.anomalies && <p className="text-xs text-danger">Anomalies : {ecg.anomalies}</p>}
        </div>
      )}
      addForm={{
        fields: CARDIO_ECG_RESULT_FIELDS,
        values: form.values,
        errors: form.errors,
        onChange: form.onChange,
        onSubmit: handleSubmit,
        isSubmitting: addMutation.isPending,
        globalError: form.globalError,
        submitLabel: "Enregistrer l'ECG",
      }}
    />
  );
}
