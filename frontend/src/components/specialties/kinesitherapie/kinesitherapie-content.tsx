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
  useAddKineSession,
  useCreateKineProgram,
  useKineProgram,
} from "@/hooks/specialties/use-kinesitherapie";
import { useSiteSelection } from "@/hooks/use-site-selection";
import { useSpecialtyAddForm } from "@/hooks/specialties/use-specialty-add-form";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate, formatDateTime } from "@/lib/datetime";
import { KINE_PROGRAM_FIELDS, KINE_SESSION_FIELDS } from "@/lib/specialty-configs/kinesitherapie";
import { buildSpecialtyPayload } from "@/lib/specialty-validation";
import type { KineSession } from "@/types/specialty";

export function KinesitherapieContent({ patientId }: { patientId: number }) {
  const siteSelection = useSiteSelection();
  const programQuery = useKineProgram(patientId);
  const openConsultationQuery = useOpenConsultation(patientId);
  const createMutation = useCreateKineProgram(patientId);
  const form = useSpecialtyAddForm(KINE_PROGRAM_FIELDS);

  if (programQuery.isLoading) return <TableSkeleton rows={4} columns={2} />;
  if (programQuery.isError) {
    return <ErrorState message={apiErrorMessage(programQuery.error)} onRetry={() => programQuery.refetch()} />;
  }

  if (!programQuery.data) {
    const siteId = siteSelection.siteId;
    const showSiteSelector = siteSelection.needsManualSelection;

    function handleCreate() {
      form.setGlobalError(null);
      if (!form.validate()) return;
      if (!siteId) {
        form.setGlobalError("Aucun site n'est associé à votre compte — impossible de créer le programme.");
        return;
      }
      createMutation.mutate(
        {
          ...buildSpecialtyPayload(KINE_PROGRAM_FIELDS, form.values),
          site_id: siteId,
          consultation_id: openConsultationQuery.data?.id ?? null,
        },
        { onError: (error) => form.setGlobalError(apiErrorMessage(error)) },
      );
    }

    return (
      <Card>
        <CardHeader>
          <CardTitle>Démarrer un programme de kinésithérapie</CardTitle>
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
            fields={KINE_PROGRAM_FIELDS}
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
            Démarrer le programme
          </Button>
        </CardContent>
      </Card>
    );
  }

  const program = programQuery.data;

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>Programme de kinésithérapie</CardTitle>
          <Badge status={program.status === "actif" ? "success" : "neutral"}>
            {program.status === "actif" ? "Actif" : "Terminé"}
          </Badge>
        </CardHeader>
        <CardContent className="flex flex-wrap gap-6 text-sm text-text">
          <p>
            <span className="text-text-subtle">Zone traitée : </span>
            {program.affected_area}
          </p>
          {program.initial_range_of_motion && (
            <p>
              <span className="text-text-subtle">Amplitude initiale : </span>
              {program.initial_range_of_motion}
            </p>
          )}
          {program.initial_pain_scale !== null && (
            <p>
              <span className="text-text-subtle">Douleur initiale : </span>
              {program.initial_pain_scale}/10
            </p>
          )}
          <p>
            <span className="text-text-subtle">Début : </span>
            {formatDate(program.started_at)}
          </p>
          {program.objectives && (
            <p className="w-full text-xs text-text-muted">Objectifs : {program.objectives}</p>
          )}
        </CardContent>
      </Card>

      <SpecialtyDiagnosisCard consultationId={program.consultation_id} />

      <SessionsSection patientId={patientId} programId={program.id} sessions={program.sessions} />
    </div>
  );
}

function SessionsSection({
  patientId,
  programId,
  sessions,
}: {
  patientId: number;
  programId: number;
  sessions: KineSession[];
}) {
  const form = useSpecialtyAddForm(KINE_SESSION_FIELDS);
  const addMutation = useAddKineSession(patientId, programId);

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    addMutation.mutate(buildSpecialtyPayload(KINE_SESSION_FIELDS, form.values), {
      onSuccess: form.reset,
      onError: (error) => form.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title="Séances"
      items={[...sessions].sort((a, b) => b.session_date.localeCompare(a.session_date))}
      emptyLabel="Aucune séance enregistrée."
      addButtonLabel="Enregistrer une séance"
      renderItem={(session) => (
        <div className="rounded-md border border-border bg-surface px-4 py-3 text-sm text-text">
          <p className="font-medium">{formatDateTime(session.session_date)}</p>
          {session.exercises_performed && (
            <p className="text-xs text-text-muted">Exercices : {session.exercises_performed}</p>
          )}
          {session.evolution && <p className="text-xs text-text-muted">Évolution : {session.evolution}</p>}
          {session.pain_scale !== null && (
            <p className="text-xs text-text-subtle">Douleur : {session.pain_scale}/10</p>
          )}
          {session.observations && (
            <p className="text-xs text-text-muted">Observations : {session.observations}</p>
          )}
        </div>
      )}
      addForm={{
        fields: KINE_SESSION_FIELDS,
        values: form.values,
        errors: form.errors,
        onChange: form.onChange,
        onSubmit: handleSubmit,
        isSubmitting: addMutation.isPending,
        globalError: form.globalError,
        submitLabel: "Enregistrer la séance",
      }}
    />
  );
}
