import { LoaderCircle } from "lucide-react";
import { useState } from "react";
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
import { useSiteSelection } from "@/hooks/use-site-selection";
import { useSpecialtyAddForm } from "@/hooks/specialties/use-specialty-add-form";
import {
  useAddNewborn,
  useAddPostpartumVisit,
  useAddPrenatalVisit,
  useCreateDelivery,
  useCreateMaternityRecord,
  useCreatePartogram,
  useAddPartogramReading,
  useMaternityRecord,
} from "@/hooks/specialties/use-maternity";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate, formatDateTime } from "@/lib/datetime";
import {
  MATERNITY_DELIVERY_FIELDS,
  MATERNITY_NEWBORN_FIELDS,
  MATERNITY_PARTOGRAM_FIELDS,
  MATERNITY_PARTOGRAM_READING_FIELDS,
  MATERNITY_POSTPARTUM_VISIT_FIELDS,
  MATERNITY_PRENATAL_VISIT_FIELDS,
  MATERNITY_RECORD_FIELDS,
} from "@/lib/specialty-configs/maternite";
import { buildSpecialtyPayload, emptySpecialtyValues, validateSpecialtyFields } from "@/lib/specialty-validation";
import type { SpecialtyFieldValues } from "@/types/specialty-config";
import type { MaternityDelivery, MaternityNewborn, MaternityPartogram, MaternityPostpartumVisit, MaternityPrenatalVisit } from "@/types/specialty";

export function MaterniteContent({ patientId }: { patientId: number }) {
  const recordQuery = useMaternityRecord(patientId);

  if (recordQuery.isLoading) return <TableSkeleton rows={4} columns={2} />;
  if (recordQuery.isError) {
    return <ErrorState message={apiErrorMessage(recordQuery.error)} onRetry={() => recordQuery.refetch()} />;
  }

  if (!recordQuery.data) {
    return <CreateMaternityRecord patientId={patientId} />;
  }

  const record = recordQuery.data;

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>Suivi de grossesse</CardTitle>
          <Badge status={record.status === "accouchee" ? "success" : "accent"}>
            {record.status === "accouchee" ? "Accouchée" : "En suivi"}
          </Badge>
        </CardHeader>
        <CardContent>
          <SpecialtyForm
            columns={2}
            fields={[
              { key: "last_menstrual_period_date", label: "DDR", type: "readonly" },
              {
                key: "estimated_delivery_date",
                label: "DPA (calculée)",
                type: "readonly",
                help: "Toujours calculée côté serveur à partir de la DDR — jamais recalculée ici.",
              },
            ]}
            values={{
              last_menstrual_period_date: record.last_menstrual_period_date ? formatDate(record.last_menstrual_period_date) : "",
              estimated_delivery_date: record.estimated_delivery_date ? formatDate(record.estimated_delivery_date) : "",
            }}
            onChange={() => {}}
          />
        </CardContent>
      </Card>

      <SpecialtyDiagnosisCard consultationId={record.consultation_id} />

      <PrenatalVisitsSection patientId={patientId} recordId={record.id} visits={record.prenatal_visits} />

      <PartogramSection patientId={patientId} recordId={record.id} partogram={record.partogram} />

      <DeliverySection patientId={patientId} recordId={record.id} delivery={record.delivery} />

      <PostpartumVisitsSection patientId={patientId} recordId={record.id} visits={record.postpartum_visits} />
    </div>
  );
}

function CreateMaternityRecord({ patientId }: { patientId: number }) {
  const [values, setValues] = useState<SpecialtyFieldValues>(() => emptySpecialtyValues(MATERNITY_RECORD_FIELDS));
  const [errors, setErrors] = useState<Partial<Record<string, string>>>({});
  const [globalError, setGlobalError] = useState<string | null>(null);
  const openConsultationQuery = useOpenConsultation(patientId);
  const createMutation = useCreateMaternityRecord(patientId);
  const siteSelection = useSiteSelection();
  const siteId = siteSelection.siteId;
  const showSiteSelector = siteSelection.needsManualSelection;

  function handleSubmit() {
    setGlobalError(null);
    const nextErrors = validateSpecialtyFields(MATERNITY_RECORD_FIELDS, values);
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) return;
    if (!siteId) {
      setGlobalError("Aucun site n'est associé à votre compte — impossible de créer le suivi.");
      return;
    }
    createMutation.mutate(
      {
        site_id: siteId,
        last_menstrual_period_date: values.last_menstrual_period_date,
        consultation_id: openConsultationQuery.data?.id ?? null,
      },
      { onError: (error) => setGlobalError(apiErrorMessage(error)) },
    );
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Démarrer un suivi de grossesse</CardTitle>
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
          fields={MATERNITY_RECORD_FIELDS}
          values={values}
          errors={errors}
          disabled={createMutation.isPending}
          onChange={(key, value) => setValues((v) => ({ ...v, [key]: value }))}
        />
        {globalError && (
          <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{globalError}</p>
        )}
        <Button onClick={handleSubmit} disabled={createMutation.isPending}>
          {createMutation.isPending && <LoaderCircle size={16} className="animate-spin" />}
          Démarrer le suivi
        </Button>
      </CardContent>
    </Card>
  );
}

function PrenatalVisitsSection({
  patientId,
  recordId,
  visits,
}: {
  patientId: number;
  recordId: number;
  visits: MaternityPrenatalVisit[];
}) {
  const form = useSpecialtyAddForm(MATERNITY_PRENATAL_VISIT_FIELDS);
  const addMutation = useAddPrenatalVisit(patientId, recordId);

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    addMutation.mutate(buildSpecialtyPayload(MATERNITY_PRENATAL_VISIT_FIELDS, form.values), {
      onSuccess: form.reset,
      onError: (error) => form.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title="Consultations prénatales (CPN)"
      items={[...visits].sort((a, b) => a.visit_number - b.visit_number)}
      emptyLabel="Aucune consultation prénatale enregistrée."
      addButtonLabel="Ajouter une CPN"
      renderItem={(visit) => (
        <div className="flex items-center justify-between gap-4 rounded-md border border-border bg-surface px-4 py-3">
          <div>
            <p className="text-sm font-medium text-text">
              CPN n°{visit.visit_number} — {visit.gestational_age_weeks} SA
            </p>
            <p className="text-xs text-text-subtle">{formatDate(visit.visit_date)}</p>
          </div>
          <div className="text-right text-xs text-text-muted">
            {visit.weight_kg && <p>{visit.weight_kg} kg</p>}
            {visit.blood_pressure_systolic && visit.blood_pressure_diastolic && (
              <p>
                {visit.blood_pressure_systolic}/{visit.blood_pressure_diastolic} mmHg
              </p>
            )}
          </div>
        </div>
      )}
      addForm={{
        fields: MATERNITY_PRENATAL_VISIT_FIELDS,
        values: form.values,
        errors: form.errors,
        onChange: form.onChange,
        onSubmit: handleSubmit,
        isSubmitting: addMutation.isPending,
        globalError: form.globalError,
        submitLabel: "Enregistrer la CPN",
      }}
    />
  );
}

function PartogramSection({
  patientId,
  recordId,
  partogram,
}: {
  patientId: number;
  recordId: number;
  partogram: MaternityPartogram | null;
}) {
  const startForm = useSpecialtyAddForm(MATERNITY_PARTOGRAM_FIELDS);
  const createMutation = useCreatePartogram(patientId, recordId);
  const readingForm = useSpecialtyAddForm(MATERNITY_PARTOGRAM_READING_FIELDS);
  const addReadingMutation = useAddPartogramReading(patientId, partogram?.id ?? 0);

  if (!partogram) {
    function handleStart() {
      startForm.setGlobalError(null);
      if (!startForm.validate()) return;
      createMutation.mutate(buildSpecialtyPayload(MATERNITY_PARTOGRAM_FIELDS, startForm.values), {
        onError: (error) => startForm.setGlobalError(apiErrorMessage(error)),
      });
    }

    return (
      <Card>
        <CardHeader>
          <CardTitle>Partogramme</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <p className="text-xs text-text-subtle">Aucun travail en cours enregistré pour cette grossesse.</p>
          <SpecialtyForm
            columns={2}
            fields={MATERNITY_PARTOGRAM_FIELDS}
            values={startForm.values}
            errors={startForm.errors}
            disabled={createMutation.isPending}
            onChange={startForm.onChange}
          />
          {startForm.globalError && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
              {startForm.globalError}
            </p>
          )}
          <Button onClick={handleStart} disabled={createMutation.isPending}>
            {createMutation.isPending && <LoaderCircle size={16} className="animate-spin" />}
            Démarrer le partogramme
          </Button>
        </CardContent>
      </Card>
    );
  }

  function handleAddReading() {
    readingForm.setGlobalError(null);
    if (!readingForm.validate()) return;
    addReadingMutation.mutate(buildSpecialtyPayload(MATERNITY_PARTOGRAM_READING_FIELDS, readingForm.values), {
      onSuccess: readingForm.reset,
      onError: (error) => readingForm.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title={`Partogramme — travail débuté le ${formatDateTime(partogram.labor_started_at)}`}
      items={[...partogram.readings].sort((a, b) => a.recorded_at.localeCompare(b.recorded_at))}
      emptyLabel="Aucune mesure enregistrée."
      addButtonLabel="Ajouter une mesure"
      renderItem={(reading) => (
        <div className="flex items-center justify-between gap-4 rounded-md border border-border bg-surface px-4 py-3">
          <div>
            <p className="text-sm font-medium text-text">Dilatation {reading.cervical_dilation_cm} cm</p>
            <p className="text-xs text-text-subtle">{formatDateTime(reading.recorded_at)}</p>
          </div>
          <div className="text-right text-xs text-text-muted">
            {reading.fetal_heart_rate && <p>BCF {reading.fetal_heart_rate} bpm</p>}
            {reading.contractions_per_10min !== null && <p>{reading.contractions_per_10min} contr./10min</p>}
          </div>
        </div>
      )}
      addForm={{
        fields: MATERNITY_PARTOGRAM_READING_FIELDS,
        values: readingForm.values,
        errors: readingForm.errors,
        onChange: readingForm.onChange,
        onSubmit: handleAddReading,
        isSubmitting: addReadingMutation.isPending,
        globalError: readingForm.globalError,
        submitLabel: "Enregistrer la mesure",
      }}
    />
  );
}

function DeliverySection({
  patientId,
  recordId,
  delivery,
}: {
  patientId: number;
  recordId: number;
  delivery: MaternityDelivery | null;
}) {
  const deliveryForm = useSpecialtyAddForm(MATERNITY_DELIVERY_FIELDS);
  const createDeliveryMutation = useCreateDelivery(patientId, recordId);
  const newbornForm = useSpecialtyAddForm(MATERNITY_NEWBORN_FIELDS);
  const addNewbornMutation = useAddNewborn(patientId, delivery?.id ?? 0);

  if (!delivery) {
    function handleRecordDelivery() {
      deliveryForm.setGlobalError(null);
      if (!deliveryForm.validate()) return;
      createDeliveryMutation.mutate(buildSpecialtyPayload(MATERNITY_DELIVERY_FIELDS, deliveryForm.values), {
        onError: (error) => deliveryForm.setGlobalError(apiErrorMessage(error)),
      });
    }

    return (
      <Card>
        <CardHeader>
          <CardTitle>Accouchement</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <SpecialtyForm
            columns={2}
            fields={MATERNITY_DELIVERY_FIELDS}
            values={deliveryForm.values}
            errors={deliveryForm.errors}
            disabled={createDeliveryMutation.isPending}
            onChange={deliveryForm.onChange}
          />
          {deliveryForm.globalError && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
              {deliveryForm.globalError}
            </p>
          )}
          <Button onClick={handleRecordDelivery} disabled={createDeliveryMutation.isPending}>
            {createDeliveryMutation.isPending && <LoaderCircle size={16} className="animate-spin" />}
            Enregistrer l'accouchement
          </Button>
        </CardContent>
      </Card>
    );
  }

  function handleAddNewborn() {
    newbornForm.setGlobalError(null);
    if (!newbornForm.validate()) return;
    addNewbornMutation.mutate(buildSpecialtyPayload(MATERNITY_NEWBORN_FIELDS, newbornForm.values), {
      onSuccess: newbornForm.reset,
      onError: (error) => newbornForm.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>Accouchement</CardTitle>
        </CardHeader>
        <CardContent className="flex flex-wrap gap-6 text-sm text-text">
          <p>
            <span className="text-text-subtle">Mode : </span>
            {delivery.mode === "cesarienne" ? "Césarienne" : "Voie basse"}
          </p>
          <p>
            <span className="text-text-subtle">Date : </span>
            {formatDateTime(delivery.delivered_at)}
          </p>
          {delivery.complications && (
            <p>
              <span className="text-text-subtle">Complications : </span>
              {delivery.complications}
            </p>
          )}
        </CardContent>
      </Card>

      <SpecialtyHistorySection
        title="Nouveau-nés"
        items={delivery.newborns}
        emptyLabel="Aucun nouveau-né enregistré."
        addButtonLabel="Ajouter un nouveau-né"
        renderItem={(newborn: MaternityNewborn) => (
          <div className="flex items-center justify-between gap-4 rounded-md border border-border bg-surface px-4 py-3">
            <p className="text-sm font-medium text-text">
              {newborn.sex === "m" ? "Garçon" : "Fille"} — {newborn.birth_weight_grams} g
            </p>
            <p className="text-xs text-text-muted">
              Apgar {newborn.apgar_1min}/{newborn.apgar_5min}
              {newborn.apgar_10min !== null ? `/${newborn.apgar_10min}` : ""}
            </p>
          </div>
        )}
        addForm={{
          fields: MATERNITY_NEWBORN_FIELDS,
          values: newbornForm.values,
          errors: newbornForm.errors,
          onChange: newbornForm.onChange,
          onSubmit: handleAddNewborn,
          isSubmitting: addNewbornMutation.isPending,
          globalError: newbornForm.globalError,
          submitLabel: "Enregistrer le nouveau-né",
        }}
      />
    </div>
  );
}

function PostpartumVisitsSection({
  patientId,
  recordId,
  visits,
}: {
  patientId: number;
  recordId: number;
  visits: MaternityPostpartumVisit[];
}) {
  const form = useSpecialtyAddForm(MATERNITY_POSTPARTUM_VISIT_FIELDS);
  const addMutation = useAddPostpartumVisit(patientId, recordId);

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    addMutation.mutate(buildSpecialtyPayload(MATERNITY_POSTPARTUM_VISIT_FIELDS, form.values), {
      onSuccess: form.reset,
      onError: (error) => form.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title="Suites de couches"
      items={visits}
      emptyLabel="Aucune visite postnatale enregistrée."
      addButtonLabel="Ajouter une visite"
      renderItem={(visit) => (
        <div className="flex items-center justify-between gap-4 rounded-md border border-border bg-surface px-4 py-3">
          <p className="text-sm text-text">{formatDate(visit.visit_date)}</p>
          <div className="text-right text-xs text-text-muted">
            {visit.bleeding_status && <p>Saignements : {visit.bleeding_status}</p>}
            {visit.temperature_c && <p>{visit.temperature_c} °C</p>}
          </div>
        </div>
      )}
      addForm={{
        fields: MATERNITY_POSTPARTUM_VISIT_FIELDS,
        values: form.values,
        errors: form.errors,
        onChange: form.onChange,
        onSubmit: handleSubmit,
        isSubmitting: addMutation.isPending,
        globalError: form.globalError,
        submitLabel: "Enregistrer la visite",
      }}
    />
  );
}
