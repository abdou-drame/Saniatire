import { LoaderCircle } from "lucide-react";
import { SpecialtyDiagnosisCard } from "@/components/clinical/specialty-diagnosis-card";
import { SpecialtyForm } from "@/components/clinical/specialty-form";
import { SpecialtyHistorySection } from "@/components/clinical/specialty-history-section";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { useAuth } from "@/hooks/use-auth";
import { useOpenConsultation } from "@/hooks/specialties/use-open-consultation";
import {
  useAddMentalHealthScaleScore,
  useCreateMentalHealthRecord,
  useMentalHealthRecord,
} from "@/hooks/specialties/use-sante-mentale";
import { useSpecialtyAddForm } from "@/hooks/specialties/use-specialty-add-form";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import { MENTAL_HEALTH_RECORD_FIELDS, MENTAL_HEALTH_SCALE_SCORE_FIELDS } from "@/lib/specialty-configs/sante-mentale";
import { buildSpecialtyPayload } from "@/lib/specialty-validation";
import type { MentalHealthScaleScore } from "@/types/specialty";

export function SanteMentaleContent({ patientId }: { patientId: number }) {
  const { user } = useAuth();
  const recordQuery = useMentalHealthRecord(patientId);
  const openConsultationQuery = useOpenConsultation(patientId);
  const createMutation = useCreateMentalHealthRecord(patientId);
  const form = useSpecialtyAddForm(MENTAL_HEALTH_RECORD_FIELDS);

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
          ...buildSpecialtyPayload(MENTAL_HEALTH_RECORD_FIELDS, form.values),
          site_id: siteId,
          consultation_id: openConsultationQuery.data?.id ?? null,
        },
        { onError: (error) => form.setGlobalError(apiErrorMessage(error)) },
      );
    }

    return (
      <Card>
        <CardHeader>
          <CardTitle>Créer le dossier de santé mentale</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <SpecialtyForm
            columns={2}
            fields={MENTAL_HEALTH_RECORD_FIELDS}
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
          <CardTitle>Dossier de santé mentale</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2 text-sm text-text">
          <p>
            <span className="text-text-subtle">Motif : </span>
            {record.consultation_reason}
          </p>
          {record.clinical_evaluation && (
            <p>
              <span className="text-text-subtle">Évaluation clinique : </span>
              {record.clinical_evaluation}
            </p>
          )}
          {record.ongoing_treatment && (
            <p>
              <span className="text-text-subtle">Suivi thérapeutique : </span>
              {record.ongoing_treatment}
            </p>
          )}
        </CardContent>
      </Card>

      <SpecialtyDiagnosisCard consultationId={record.consultation_id} />

      <ScaleScoresSection patientId={patientId} recordId={record.id} scores={record.scale_scores} />
    </div>
  );
}

function ScaleScoresSection({
  patientId,
  recordId,
  scores,
}: {
  patientId: number;
  recordId: number;
  scores: MentalHealthScaleScore[];
}) {
  const form = useSpecialtyAddForm(MENTAL_HEALTH_SCALE_SCORE_FIELDS);
  const addMutation = useAddMentalHealthScaleScore(patientId, recordId);

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    addMutation.mutate(buildSpecialtyPayload(MENTAL_HEALTH_SCALE_SCORE_FIELDS, form.values), {
      onSuccess: form.reset,
      onError: (error) => form.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title="Échelles cliniques"
      items={[...scores].sort((a, b) => b.scored_at.localeCompare(a.scored_at))}
      emptyLabel="Aucune échelle enregistrée."
      addButtonLabel="Ajouter un score"
      renderItem={(score) => (
        <div className="rounded-md border border-border bg-surface px-4 py-3 text-sm text-text">
          <p className="font-medium">
            {score.scale_name} — {score.score}
          </p>
          <p className="text-xs text-text-subtle">{formatDate(score.scored_at)}</p>
        </div>
      )}
      addForm={{
        fields: MENTAL_HEALTH_SCALE_SCORE_FIELDS,
        values: form.values,
        errors: form.errors,
        onChange: form.onChange,
        onSubmit: handleSubmit,
        isSubmitting: addMutation.isPending,
        globalError: form.globalError,
        submitLabel: "Enregistrer le score",
      }}
    />
  );
}
