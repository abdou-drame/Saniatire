import { LoaderCircle } from "lucide-react";
import { useState } from "react";
import { SpecialtyDiagnosisCard } from "@/components/clinical/specialty-diagnosis-card";
import { SpecialtyForm } from "@/components/clinical/specialty-form";
import { SpecialtyHistorySection } from "@/components/clinical/specialty-history-section";
import { DryWeightChart } from "@/components/specialties/dialyse/dry-weight-chart";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { useAuth } from "@/hooks/use-auth";
import { useOpenConsultation } from "@/hooks/specialties/use-open-consultation";
import {
  useAddDialysisSession,
  useAddSessionVital,
  useCreateDialysisProgram,
  useDialysisProgram,
} from "@/hooks/specialties/use-dialysis";
import { useSpecialtyAddForm } from "@/hooks/specialties/use-specialty-add-form";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate, formatDateTime } from "@/lib/datetime";
import {
  DIALYSIS_PROGRAM_FIELDS,
  DIALYSIS_SESSION_FIELDS,
  DIALYSIS_SESSION_VITAL_FIELDS,
} from "@/lib/specialty-configs/dialyse";
import { buildSpecialtyPayload } from "@/lib/specialty-validation";
import type { DialysisSession } from "@/types/specialty";

export function DialyseContent({ patientId }: { patientId: number }) {
  const { user } = useAuth();
  const programQuery = useDialysisProgram(patientId);
  const openConsultationQuery = useOpenConsultation(patientId);
  const createMutation = useCreateDialysisProgram(patientId);
  const form = useSpecialtyAddForm(DIALYSIS_PROGRAM_FIELDS);

  if (programQuery.isLoading) return <TableSkeleton rows={4} columns={2} />;
  if (programQuery.isError) {
    return <ErrorState message={apiErrorMessage(programQuery.error)} onRetry={() => programQuery.refetch()} />;
  }

  if (!programQuery.data) {
    const siteId = user?.sites[0]?.id ?? null;

    function handleCreate() {
      form.setGlobalError(null);
      if (!form.validate()) return;
      if (!siteId) {
        form.setGlobalError("Aucun site n'est associé à votre compte — impossible de créer le programme.");
        return;
      }
      createMutation.mutate(
        {
          ...buildSpecialtyPayload(DIALYSIS_PROGRAM_FIELDS, form.values),
          site_id: siteId,
          consultation_id: openConsultationQuery.data?.id ?? null,
        },
        { onError: (error) => form.setGlobalError(apiErrorMessage(error)) },
      );
    }

    return (
      <Card>
        <CardHeader>
          <CardTitle>Démarrer un programme de dialyse</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <SpecialtyForm
            columns={2}
            fields={DIALYSIS_PROGRAM_FIELDS}
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
          <CardTitle>Programme de dialyse</CardTitle>
          <Badge status={program.status === "actif" ? "success" : program.status === "suspendu" ? "warning" : "neutral"}>
            {program.status === "actif" ? "Actif" : program.status === "suspendu" ? "Suspendu" : "Arrêté"}
          </Badge>
        </CardHeader>
        <CardContent className="flex flex-wrap gap-6 text-sm text-text">
          <p>
            <span className="text-text-subtle">Fréquence : </span>
            {program.frequency_per_week} séances / semaine
          </p>
          <p>
            <span className="text-text-subtle">Poids sec cible : </span>
            {program.dry_weight_kg} kg
          </p>
          <p>
            <span className="text-text-subtle">Abord vasculaire : </span>
            {program.vascular_access_type}
            {program.vascular_access_status && ` (${program.vascular_access_status})`}
          </p>
          <p>
            <span className="text-text-subtle">Début : </span>
            {formatDate(program.started_at)}
          </p>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Évolution du poids sec</CardTitle>
        </CardHeader>
        <CardContent>
          <DryWeightChart sessions={program.sessions} />
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
  sessions: DialysisSession[];
}) {
  const form = useSpecialtyAddForm(DIALYSIS_SESSION_FIELDS);
  const addMutation = useAddDialysisSession(patientId, programId);

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    addMutation.mutate(buildSpecialtyPayload(DIALYSIS_SESSION_FIELDS, form.values), {
      onSuccess: form.reset,
      onError: (error) => form.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title="Séances de dialyse"
      items={[...sessions].sort((a, b) => b.session_date.localeCompare(a.session_date))}
      emptyLabel="Aucune séance enregistrée."
      addButtonLabel="Enregistrer une séance"
      renderItem={(session) => <SessionRow patientId={patientId} session={session} />}
      addForm={{
        fields: DIALYSIS_SESSION_FIELDS,
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

function SessionRow({ patientId, session }: { patientId: number; session: DialysisSession }) {
  const [addingVital, setAddingVital] = useState(false);
  const vitalForm = useSpecialtyAddForm(DIALYSIS_SESSION_VITAL_FIELDS);
  const addVitalMutation = useAddSessionVital(patientId, session.id);

  function handleAddVital() {
    vitalForm.setGlobalError(null);
    if (!vitalForm.validate()) return;
    addVitalMutation.mutate(buildSpecialtyPayload(DIALYSIS_SESSION_VITAL_FIELDS, vitalForm.values), {
      onSuccess: () => {
        vitalForm.reset();
        setAddingVital(false);
      },
      onError: (error) => vitalForm.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <div className="space-y-2 rounded-md border border-border bg-surface px-4 py-3">
      <div className="flex items-center justify-between gap-4">
        <div>
          <p className="text-sm font-medium text-text">{formatDateTime(session.session_date)}</p>
          <p className="text-xs text-text-subtle">
            {session.pre_weight_kg} kg → {session.post_weight_kg ?? "—"} kg
            {session.duration_minutes && ` · ${session.duration_minutes} min`}
          </p>
        </div>
        <Badge status={session.status === "terminee" ? "success" : "danger"}>
          {session.status === "terminee" ? "Terminée" : "Interrompue"}
        </Badge>
      </div>

      {session.complications && (
        <p className="rounded-md bg-danger/10 px-2.5 py-1.5 text-xs text-danger">{session.complications}</p>
      )}

      {session.vitals.length > 0 && (
        <ul className="space-y-1 text-xs text-text-muted">
          {session.vitals.map((vital) => (
            <li key={vital.id}>
              {formatDateTime(vital.measured_at)} — TA {vital.blood_pressure_systolic}/{vital.blood_pressure_diastolic}{" "}
              mmHg, FC {vital.heart_rate} bpm
            </li>
          ))}
        </ul>
      )}

      {!addingVital && (
        <Button variant="ghost" size="sm" onClick={() => setAddingVital(true)}>
          Ajouter une mesure en cours de séance
        </Button>
      )}

      {addingVital && (
        <div className="space-y-3 rounded-md border border-border bg-bg p-3">
          <SpecialtyForm
            columns={3}
            fields={DIALYSIS_SESSION_VITAL_FIELDS}
            values={vitalForm.values}
            errors={vitalForm.errors}
            disabled={addVitalMutation.isPending}
            onChange={vitalForm.onChange}
          />
          {vitalForm.globalError && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
              {vitalForm.globalError}
            </p>
          )}
          <div className="flex gap-2">
            <Button size="sm" disabled={addVitalMutation.isPending} onClick={handleAddVital}>
              {addVitalMutation.isPending && <LoaderCircle size={14} className="animate-spin" />}
              Enregistrer
            </Button>
            <Button variant="ghost" size="sm" onClick={() => setAddingVital(false)}>
              Annuler
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
