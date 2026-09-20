import { LoaderCircle } from "lucide-react";
import { SpecialtyDiagnosisCard } from "@/components/clinical/specialty-diagnosis-card";
import { SpecialtyForm } from "@/components/clinical/specialty-form";
import { SpecialtyHistorySection } from "@/components/clinical/specialty-history-section";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { SiteSelectField } from "@/components/clinical/site-select-field";
import { useOpenConsultation } from "@/hooks/specialties/use-open-consultation";
import {
  useAddOncoChemoCycle,
  useAddOncoResponseEvaluation,
  useCreateOncoRecord,
  useOncoRecord,
} from "@/hooks/specialties/use-oncologie";
import { useSiteSelection } from "@/hooks/use-site-selection";
import { useSpecialtyAddForm } from "@/hooks/specialties/use-specialty-add-form";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import {
  ONCO_CHEMO_CYCLE_FIELDS,
  ONCO_RECORD_FIELDS,
  ONCO_RESPONSE_EVALUATION_FIELDS,
} from "@/lib/specialty-configs/oncologie";
import { buildSpecialtyPayload } from "@/lib/specialty-validation";
import type { OncoChemoCycle, OncoResponseEvaluation } from "@/types/specialty";

const RESPONSE_LABELS: Record<string, string> = {
  reponse_complete: "Réponse complète",
  reponse_partielle: "Réponse partielle",
  stable: "Stable",
  progression: "Progression",
};

const RESPONSE_STATUS: Record<string, "success" | "warning" | "neutral" | "danger"> = {
  reponse_complete: "success",
  reponse_partielle: "warning",
  stable: "neutral",
  progression: "danger",
};

export function OncologieContent({ patientId }: { patientId: number }) {
  const siteSelection = useSiteSelection();
  const recordQuery = useOncoRecord(patientId);
  const openConsultationQuery = useOpenConsultation(patientId);
  const createMutation = useCreateOncoRecord(patientId);
  const form = useSpecialtyAddForm(ONCO_RECORD_FIELDS);

  if (recordQuery.isLoading) return <TableSkeleton rows={4} columns={2} />;
  if (recordQuery.isError) {
    return <ErrorState message={apiErrorMessage(recordQuery.error)} onRetry={() => recordQuery.refetch()} />;
  }

  if (!recordQuery.data) {
    const siteId = siteSelection.siteId;
    const showSiteSelector = siteSelection.needsManualSelection;

    function handleCreate() {
      form.setGlobalError(null);
      if (!form.validate()) return;
      if (!siteId) {
        form.setGlobalError("Aucun site n'est associé à votre compte — impossible de créer le dossier.");
        return;
      }
      createMutation.mutate(
        {
          ...buildSpecialtyPayload(ONCO_RECORD_FIELDS, form.values),
          site_id: siteId,
          consultation_id: openConsultationQuery.data?.id ?? null,
        },
        { onError: (error) => form.setGlobalError(apiErrorMessage(error)) },
      );
    }

    return (
      <Card>
        <CardHeader>
          <CardTitle>Créer le dossier d'oncologie</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {showSiteSelector && (
            <SiteSelectField
              siteId={siteSelection.siteId}
              onChange={siteSelection.setSiteId}
              options={siteSelection.options}
              isLoading={siteSelection.isLoading}
            />
          )}
          <SpecialtyForm
            columns={2}
            fields={ONCO_RECORD_FIELDS}
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
  const stage = [record.stage_t, record.stage_n, record.stage_m].filter(Boolean).join(" ");

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>Dossier d'oncologie</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-wrap gap-6 text-sm text-text">
          <p>
            <span className="text-text-subtle">Type de cancer : </span>
            {record.cancer_type}
          </p>
          {stage && (
            <p>
              <span className="text-text-subtle">Stade : </span>
              {stage}
            </p>
          )}
          {record.protocol_name && (
            <p>
              <span className="text-text-subtle">Protocole : </span>
              {record.protocol_name}
            </p>
          )}
          {record.treatment_line !== null && (
            <p>
              <span className="text-text-subtle">Ligne de traitement : </span>
              {record.treatment_line}
            </p>
          )}
          {record.diagnosed_at && (
            <p>
              <span className="text-text-subtle">Diagnostic : </span>
              {formatDate(record.diagnosed_at)}
            </p>
          )}
        </CardContent>
      </Card>

      <SpecialtyDiagnosisCard consultationId={record.consultation_id} />

      <ChemoCyclesSection patientId={patientId} recordId={record.id} cycles={record.chemo_cycles} />
      <ResponseEvaluationsSection
        patientId={patientId}
        recordId={record.id}
        evaluations={record.response_evaluations}
      />
    </div>
  );
}

function ChemoCyclesSection({
  patientId,
  recordId,
  cycles,
}: {
  patientId: number;
  recordId: number;
  cycles: OncoChemoCycle[];
}) {
  const form = useSpecialtyAddForm(ONCO_CHEMO_CYCLE_FIELDS);
  const addMutation = useAddOncoChemoCycle(patientId, recordId);

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    addMutation.mutate(buildSpecialtyPayload(ONCO_CHEMO_CYCLE_FIELDS, form.values), {
      onSuccess: form.reset,
      onError: (error) => form.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title="Cycles de chimiothérapie"
      items={[...cycles].sort((a, b) => b.cycle_number - a.cycle_number)}
      emptyLabel="Aucun cycle enregistré."
      addButtonLabel="Ajouter un cycle"
      renderItem={(cycle) => (
        <div className="rounded-md border border-border bg-surface px-4 py-3 text-sm text-text">
          <p className="font-medium">
            Cycle {cycle.cycle_number} — {formatDate(cycle.cycle_date)}
          </p>
          {cycle.medications && <p className="text-xs text-text-muted">Médicaments : {cycle.medications}</p>}
          {cycle.side_effects && <p className="text-xs text-danger">Effets secondaires : {cycle.side_effects}</p>}
        </div>
      )}
      addForm={{
        fields: ONCO_CHEMO_CYCLE_FIELDS,
        values: form.values,
        errors: form.errors,
        onChange: form.onChange,
        onSubmit: handleSubmit,
        isSubmitting: addMutation.isPending,
        globalError: form.globalError,
        submitLabel: "Enregistrer le cycle",
      }}
    />
  );
}

function ResponseEvaluationsSection({
  patientId,
  recordId,
  evaluations,
}: {
  patientId: number;
  recordId: number;
  evaluations: OncoResponseEvaluation[];
}) {
  const form = useSpecialtyAddForm(ONCO_RESPONSE_EVALUATION_FIELDS);
  const addMutation = useAddOncoResponseEvaluation(patientId, recordId);

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    addMutation.mutate(buildSpecialtyPayload(ONCO_RESPONSE_EVALUATION_FIELDS, form.values), {
      onSuccess: form.reset,
      onError: (error) => form.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title="Évaluations de la réponse au traitement"
      items={[...evaluations].sort((a, b) => b.evaluated_at.localeCompare(a.evaluated_at))}
      emptyLabel="Aucune évaluation enregistrée."
      addButtonLabel="Ajouter une évaluation"
      renderItem={(evaluation) => (
        <div className="rounded-md border border-border bg-surface px-4 py-3 text-sm text-text">
          <div className="flex items-center justify-between gap-4">
            <p className="font-medium">{formatDate(evaluation.evaluated_at)}</p>
            <Badge status={RESPONSE_STATUS[evaluation.response] ?? "neutral"}>
              {RESPONSE_LABELS[evaluation.response] ?? evaluation.response}
            </Badge>
          </div>
          {evaluation.notes && <p className="text-xs text-text-muted">{evaluation.notes}</p>}
        </div>
      )}
      addForm={{
        fields: ONCO_RESPONSE_EVALUATION_FIELDS,
        values: form.values,
        errors: form.errors,
        onChange: form.onChange,
        onSubmit: handleSubmit,
        isSubmitting: addMutation.isPending,
        globalError: form.globalError,
        submitLabel: "Enregistrer l'évaluation",
      }}
    />
  );
}
