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
  useAddPmaCycleMonitoring,
  useAddPmaStimulationProtocol,
  useCreatePmaRecord,
  usePmaRecord,
} from "@/hooks/specialties/use-pma";
import { useSiteSelection } from "@/hooks/use-site-selection";
import { useSpecialtyAddForm } from "@/hooks/specialties/use-specialty-add-form";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import {
  PMA_CYCLE_MONITORING_FIELDS,
  PMA_RECORD_FIELDS,
  PMA_STIMULATION_PROTOCOL_FIELDS,
} from "@/lib/specialty-configs/pma";
import { buildSpecialtyPayload } from "@/lib/specialty-validation";
import type { PmaCycleMonitoring, PmaStimulationProtocol } from "@/types/specialty";

const ATTEMPT_RESULT_LABELS: Record<string, string> = {
  en_cours: "En cours",
  positif: "Positif",
  negatif: "Négatif",
};

const ATTEMPT_RESULT_STATUS: Record<string, "success" | "warning" | "neutral" | "danger"> = {
  en_cours: "warning",
  positif: "success",
  negatif: "danger",
};

export function PmaContent({ patientId }: { patientId: number }) {
  const siteSelection = useSiteSelection();
  const recordQuery = usePmaRecord(patientId);
  const openConsultationQuery = useOpenConsultation(patientId);
  const createMutation = useCreatePmaRecord(patientId);
  const form = useSpecialtyAddForm(PMA_RECORD_FIELDS);

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
          ...buildSpecialtyPayload(PMA_RECORD_FIELDS, form.values),
          site_id: siteId,
          consultation_id: openConsultationQuery.data?.id ?? null,
        },
        { onError: (error) => form.setGlobalError(apiErrorMessage(error)) },
      );
    }

    return (
      <Card>
        <CardHeader>
          <CardTitle>Créer le dossier PMA / Fertilité</CardTitle>
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
            fields={PMA_RECORD_FIELDS}
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
          <CardTitle>Dossier PMA / Fertilité</CardTitle>
          <Badge status={ATTEMPT_RESULT_STATUS[record.attempt_result] ?? "neutral"}>
            {ATTEMPT_RESULT_LABELS[record.attempt_result] ?? record.attempt_result}
          </Badge>
        </CardHeader>
        <CardContent className="space-y-2 text-sm text-text">
          {record.fertility_history && (
            <p>
              <span className="text-text-subtle">Bilan de fertilité : </span>
              {record.fertility_history}
            </p>
          )}
          {record.exams_performed && (
            <p>
              <span className="text-text-subtle">Examens réalisés : </span>
              {record.exams_performed}
            </p>
          )}
        </CardContent>
      </Card>

      <SpecialtyDiagnosisCard consultationId={record.consultation_id} />

      <StimulationProtocolsSection
        patientId={patientId}
        recordId={record.id}
        protocols={record.stimulation_protocols}
      />
      <CycleMonitoringsSection patientId={patientId} recordId={record.id} monitorings={record.cycle_monitorings} />
    </div>
  );
}

function StimulationProtocolsSection({
  patientId,
  recordId,
  protocols,
}: {
  patientId: number;
  recordId: number;
  protocols: PmaStimulationProtocol[];
}) {
  const form = useSpecialtyAddForm(PMA_STIMULATION_PROTOCOL_FIELDS);
  const addMutation = useAddPmaStimulationProtocol(patientId, recordId);

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    addMutation.mutate(buildSpecialtyPayload(PMA_STIMULATION_PROTOCOL_FIELDS, form.values), {
      onSuccess: form.reset,
      onError: (error) => form.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title="Protocoles de stimulation"
      items={[...protocols].sort((a, b) => b.started_at.localeCompare(a.started_at))}
      emptyLabel="Aucun protocole enregistré."
      addButtonLabel="Ajouter un protocole"
      renderItem={(protocol) => (
        <div className="rounded-md border border-border bg-surface px-4 py-3 text-sm text-text">
          <p className="font-medium">{protocol.protocol_type}</p>
          <p className="text-xs text-text-subtle">
            {formatDate(protocol.started_at)} → {protocol.ended_at ? formatDate(protocol.ended_at) : "en cours"}
          </p>
          {protocol.medications && <p className="text-xs text-text-muted">Médicaments : {protocol.medications}</p>}
        </div>
      )}
      addForm={{
        fields: PMA_STIMULATION_PROTOCOL_FIELDS,
        values: form.values,
        errors: form.errors,
        onChange: form.onChange,
        onSubmit: handleSubmit,
        isSubmitting: addMutation.isPending,
        globalError: form.globalError,
        submitLabel: "Enregistrer le protocole",
      }}
    />
  );
}

function CycleMonitoringsSection({
  patientId,
  recordId,
  monitorings,
}: {
  patientId: number;
  recordId: number;
  monitorings: PmaCycleMonitoring[];
}) {
  const form = useSpecialtyAddForm(PMA_CYCLE_MONITORING_FIELDS);
  const addMutation = useAddPmaCycleMonitoring(patientId, recordId);

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    addMutation.mutate(buildSpecialtyPayload(PMA_CYCLE_MONITORING_FIELDS, form.values), {
      onSuccess: form.reset,
      onError: (error) => form.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title="Suivi de cycle"
      items={[...monitorings].sort((a, b) => b.monitoring_date.localeCompare(a.monitoring_date))}
      emptyLabel="Aucun suivi enregistré."
      addButtonLabel="Ajouter un suivi"
      renderItem={(monitoring) => (
        <div className="rounded-md border border-border bg-surface px-4 py-3 text-sm text-text">
          <p className="font-medium">{formatDate(monitoring.monitoring_date)}</p>
          {monitoring.echo_observations && (
            <p className="text-xs text-text-muted">Échographie : {monitoring.echo_observations}</p>
          )}
          {monitoring.hormone_level !== null && (
            <p className="text-xs text-text-subtle">Taux hormonal : {monitoring.hormone_level}</p>
          )}
          {(monitoring.puncture_date || monitoring.transfer_date) && (
            <p className="text-xs text-text-subtle">
              {monitoring.puncture_date && `Ponction : ${formatDate(monitoring.puncture_date)}`}
              {monitoring.puncture_date && monitoring.transfer_date && " · "}
              {monitoring.transfer_date && `Transfert : ${formatDate(monitoring.transfer_date)}`}
            </p>
          )}
        </div>
      )}
      addForm={{
        fields: PMA_CYCLE_MONITORING_FIELDS,
        values: form.values,
        errors: form.errors,
        onChange: form.onChange,
        onSubmit: handleSubmit,
        isSubmitting: addMutation.isPending,
        globalError: form.globalError,
        submitLabel: "Enregistrer le suivi",
      }}
    />
  );
}
