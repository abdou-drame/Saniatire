import { LoaderCircle } from "lucide-react";
import { SpecialtyDiagnosisCard } from "@/components/clinical/specialty-diagnosis-card";
import { SpecialtyHistorySection } from "@/components/clinical/specialty-history-section";
import { GrowthChart } from "@/components/specialties/pediatrie/growth-chart";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { useAuth } from "@/hooks/use-auth";
import { useOpenConsultation } from "@/hooks/specialties/use-open-consultation";
import {
  useAddPediatricDevelopmentObservation,
  useAddPediatricGrowthMeasurement,
  useAddPediatricVaccination,
  useCreatePediatricRecord,
  usePediatricRecord,
} from "@/hooks/specialties/use-pediatrie";
import { useSpecialtyAddForm } from "@/hooks/specialties/use-specialty-add-form";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import {
  PEDIATRIC_DEVELOPMENT_OBSERVATION_FIELDS,
  PEDIATRIC_GROWTH_MEASUREMENT_FIELDS,
  PEDIATRIC_RECORD_FIELDS,
  PEDIATRIC_VACCINATION_FIELDS,
} from "@/lib/specialty-configs/pediatrie";
import { buildSpecialtyPayload } from "@/lib/specialty-validation";
import type {
  PediatricDevelopmentObservation,
  PediatricGrowthMeasurement,
  PediatricVaccination,
} from "@/types/specialty";

export function PediatrieContent({ patientId }: { patientId: number }) {
  const { user } = useAuth();
  const recordQuery = usePediatricRecord(patientId);
  const openConsultationQuery = useOpenConsultation(patientId);
  const createMutation = useCreatePediatricRecord(patientId);

  if (recordQuery.isLoading) return <TableSkeleton rows={4} columns={2} />;
  if (recordQuery.isError) {
    return <ErrorState message={apiErrorMessage(recordQuery.error)} onRetry={() => recordQuery.refetch()} />;
  }

  if (!recordQuery.data) {
    const siteId = user?.sites[0]?.id ?? null;

    function handleCreate() {
      if (!siteId) return;
      createMutation.mutate({
        ...buildSpecialtyPayload(PEDIATRIC_RECORD_FIELDS, {}),
        site_id: siteId,
        consultation_id: openConsultationQuery.data?.id ?? null,
      });
    }

    return (
      <Card>
        <CardHeader>
          <CardTitle>Ouvrir le dossier pédiatrique</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <p className="text-sm text-text-muted">
            Ce dossier regroupe le suivi de croissance, la couverture vaccinale et les observations de développement.
          </p>
          {!siteId && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
              Aucun site n'est associé à votre compte — impossible de créer le dossier.
            </p>
          )}
          <Button onClick={handleCreate} disabled={createMutation.isPending || !siteId}>
            {createMutation.isPending && <LoaderCircle size={16} className="animate-spin" />}
            Ouvrir le dossier
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
          <CardTitle>Courbe de croissance</CardTitle>
        </CardHeader>
        <CardContent>
          <GrowthChart measurements={record.growth_measurements} />
        </CardContent>
      </Card>

      <SpecialtyDiagnosisCard consultationId={record.consultation_id} />

      <GrowthMeasurementsSection
        patientId={patientId}
        recordId={record.id}
        measurements={record.growth_measurements}
      />
      <VaccinationsSection patientId={patientId} recordId={record.id} vaccinations={record.vaccinations} />
      <DevelopmentObservationsSection
        patientId={patientId}
        recordId={record.id}
        observations={record.development_observations}
      />
    </div>
  );
}

function GrowthMeasurementsSection({
  patientId,
  recordId,
  measurements,
}: {
  patientId: number;
  recordId: number;
  measurements: PediatricGrowthMeasurement[];
}) {
  const form = useSpecialtyAddForm(PEDIATRIC_GROWTH_MEASUREMENT_FIELDS);
  const addMutation = useAddPediatricGrowthMeasurement(patientId, recordId);

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    addMutation.mutate(buildSpecialtyPayload(PEDIATRIC_GROWTH_MEASUREMENT_FIELDS, form.values), {
      onSuccess: form.reset,
      onError: (error) => form.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title="Mesures de croissance"
      items={[...measurements].sort((a, b) => b.measured_at.localeCompare(a.measured_at))}
      emptyLabel="Aucune mesure enregistrée."
      addButtonLabel="Ajouter une mesure"
      renderItem={(measurement) => (
        <div className="rounded-md border border-border bg-surface px-4 py-3 text-sm text-text">
          <p className="font-medium">{formatDate(measurement.measured_at)}</p>
          <p className="text-xs text-text-muted">
            {measurement.weight_kg !== null && `${measurement.weight_kg} kg`}
            {measurement.height_cm !== null && ` · ${measurement.height_cm} cm`}
            {measurement.head_circumference_cm !== null && ` · PC ${measurement.head_circumference_cm} cm`}
          </p>
        </div>
      )}
      addForm={{
        fields: PEDIATRIC_GROWTH_MEASUREMENT_FIELDS,
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

function VaccinationsSection({
  patientId,
  recordId,
  vaccinations,
}: {
  patientId: number;
  recordId: number;
  vaccinations: PediatricVaccination[];
}) {
  const form = useSpecialtyAddForm(PEDIATRIC_VACCINATION_FIELDS);
  const addMutation = useAddPediatricVaccination(patientId, recordId);

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    addMutation.mutate(buildSpecialtyPayload(PEDIATRIC_VACCINATION_FIELDS, form.values), {
      onSuccess: form.reset,
      onError: (error) => form.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title="Suivi vaccinal"
      items={[...vaccinations].sort((a, b) => b.administered_at.localeCompare(a.administered_at))}
      emptyLabel="Aucune vaccination enregistrée."
      addButtonLabel="Ajouter une vaccination"
      renderItem={(vaccination) => (
        <div className="rounded-md border border-border bg-surface px-4 py-3 text-sm text-text">
          <p className="font-medium">
            {vaccination.vaccine_name}
            {vaccination.dose_number !== null && ` — dose ${vaccination.dose_number}`}
          </p>
          <p className="text-xs text-text-subtle">{formatDate(vaccination.administered_at)}</p>
        </div>
      )}
      addForm={{
        fields: PEDIATRIC_VACCINATION_FIELDS,
        values: form.values,
        errors: form.errors,
        onChange: form.onChange,
        onSubmit: handleSubmit,
        isSubmitting: addMutation.isPending,
        globalError: form.globalError,
        submitLabel: "Enregistrer la vaccination",
      }}
    />
  );
}

function DevelopmentObservationsSection({
  patientId,
  recordId,
  observations,
}: {
  patientId: number;
  recordId: number;
  observations: PediatricDevelopmentObservation[];
}) {
  const form = useSpecialtyAddForm(PEDIATRIC_DEVELOPMENT_OBSERVATION_FIELDS);
  const addMutation = useAddPediatricDevelopmentObservation(patientId, recordId);

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    addMutation.mutate(buildSpecialtyPayload(PEDIATRIC_DEVELOPMENT_OBSERVATION_FIELDS, form.values), {
      onSuccess: form.reset,
      onError: (error) => form.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title="Développement psychomoteur"
      items={[...observations].sort((a, b) => b.observed_at.localeCompare(a.observed_at))}
      emptyLabel="Aucune observation enregistrée."
      addButtonLabel="Ajouter une observation"
      renderItem={(observation) => (
        <div className="rounded-md border border-border bg-surface px-4 py-3 text-sm text-text">
          <p className="font-medium">{formatDate(observation.observed_at)} — {observation.age_months} mois</p>
          <p className="text-xs text-text-muted">{observation.observation}</p>
        </div>
      )}
      addForm={{
        fields: PEDIATRIC_DEVELOPMENT_OBSERVATION_FIELDS,
        values: form.values,
        errors: form.errors,
        onChange: form.onChange,
        onSubmit: handleSubmit,
        isSubmitting: addMutation.isPending,
        globalError: form.globalError,
        submitLabel: "Enregistrer l'observation",
      }}
    />
  );
}
