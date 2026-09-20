import { CalendarPlus, LoaderCircle } from "lucide-react";
import { useState } from "react";
import { SpecialtyDiagnosisCard } from "@/components/clinical/specialty-diagnosis-card";
import { SpecialtyForm } from "@/components/clinical/specialty-form";
import { SpecialtyHistorySection } from "@/components/clinical/specialty-history-section";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { TableSkeleton } from "@/components/ui/loading-state";
import { SiteSelectField } from "@/components/clinical/site-select-field";
import { useOpenConsultation } from "@/hooks/specialties/use-open-consultation";
import {
  useAddHomeCareVisit,
  useCreateHomeCareRecord,
  useHomeCareRecord,
} from "@/hooks/specialties/use-soins-domicile";
import { useSiteSelection } from "@/hooks/use-site-selection";
import { useSpecialtyAddForm } from "@/hooks/specialties/use-specialty-add-form";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import { HOME_CARE_RECORD_FIELDS, HOME_CARE_VISIT_FIELDS } from "@/lib/specialty-configs/soins-domicile";
import { buildSpecialtyPayload } from "@/lib/specialty-validation";
import { AppointmentFormDialog } from "@/pages/reception/appointment-form-dialog";
import type { Patient } from "@/types/api";
import type { HomeCareVisit } from "@/types/specialty";

export function SoinsDomicileContent({ patientId, patient }: { patientId: number; patient: Patient }) {
  const recordQuery = useHomeCareRecord(patientId);
  const openConsultationQuery = useOpenConsultation(patientId);
  const createMutation = useCreateHomeCareRecord(patientId);
  const form = useSpecialtyAddForm(HOME_CARE_RECORD_FIELDS);
  const [schedulingOpen, setSchedulingOpen] = useState(false);
  const siteSelection = useSiteSelection();
  const siteId = siteSelection.siteId;
  const showSiteSelector = siteSelection.needsManualSelection;

  if (recordQuery.isLoading) return <TableSkeleton rows={4} columns={2} />;
  if (recordQuery.isError) {
    return <ErrorState message={apiErrorMessage(recordQuery.error)} onRetry={() => recordQuery.refetch()} />;
  }

  if (!recordQuery.data) {
    function handleCreate() {
      form.setGlobalError(null);
      if (!form.validate()) return;
      if (!siteId) {
        form.setGlobalError("Aucun site n'est associé à votre compte — impossible de créer le dossier.");
        return;
      }
      createMutation.mutate(
        {
          ...buildSpecialtyPayload(HOME_CARE_RECORD_FIELDS, form.values),
          site_id: siteId,
          consultation_id: openConsultationQuery.data?.id ?? null,
        },
        { onError: (error) => form.setGlobalError(apiErrorMessage(error)) },
      );
    }

    return (
      <Card>
        <CardHeader>
          <CardTitle>Créer le dossier de soins à domicile</CardTitle>
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
            fields={HOME_CARE_RECORD_FIELDS}
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
          <CardTitle>Dossier de soins à domicile</CardTitle>
          {siteId && (
            <Button size="sm" variant="secondary" onClick={() => setSchedulingOpen(true)}>
              <CalendarPlus size={14} />
              Planifier le prochain passage
            </Button>
          )}
        </CardHeader>
        <CardContent className="flex flex-wrap gap-6 text-sm text-text">
          <p>
            <span className="text-text-subtle">Adresse d'intervention : </span>
            {record.intervention_address}
          </p>
          <p>
            <span className="text-text-subtle">Type de soins : </span>
            {record.care_type}
          </p>
        </CardContent>
      </Card>

      <SpecialtyDiagnosisCard consultationId={record.consultation_id} />

      <VisitsSection patientId={patientId} recordId={record.id} visits={record.visits} />

      {siteId && (
        <AppointmentFormDialog
          open={schedulingOpen}
          onOpenChange={setSchedulingOpen}
          siteId={siteId}
          lockedPatient={patient}
        />
      )}
    </div>
  );
}

function VisitsSection({
  patientId,
  recordId,
  visits,
}: {
  patientId: number;
  recordId: number;
  visits: HomeCareVisit[];
}) {
  const form = useSpecialtyAddForm(HOME_CARE_VISIT_FIELDS);
  const addMutation = useAddHomeCareVisit(patientId, recordId);

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    addMutation.mutate(buildSpecialtyPayload(HOME_CARE_VISIT_FIELDS, form.values), {
      onSuccess: form.reset,
      onError: (error) => form.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title="Comptes rendus de visite"
      items={[...visits].sort((a, b) => b.visit_datetime.localeCompare(a.visit_datetime))}
      emptyLabel="Aucune visite enregistrée."
      addButtonLabel="Enregistrer une visite"
      renderItem={(visit) => (
        <div className="rounded-md border border-border bg-surface px-4 py-3 text-sm text-text">
          <p className="font-medium">
            {formatDateTime(visit.visit_datetime)} — {visit.care_type}
          </p>
          {visit.report && <p className="text-xs text-text-muted">{visit.report}</p>}
        </div>
      )}
      addForm={{
        fields: HOME_CARE_VISIT_FIELDS,
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
