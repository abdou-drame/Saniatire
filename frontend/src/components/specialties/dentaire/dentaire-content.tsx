import { Check, LoaderCircle } from "lucide-react";
import { SpecialtyDiagnosisCard } from "@/components/clinical/specialty-diagnosis-card";
import { SpecialtyHistorySection } from "@/components/clinical/specialty-history-section";
import { Odontogram } from "@/components/specialties/dentaire/odontogram";
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
  useAddProcedure,
  useAddTreatmentPlanItem,
  useCreateDentalChart,
  useCreateTreatmentPlan,
  useDentalChart,
  useMarkTreatmentPlanItemDone,
} from "@/hooks/specialties/use-dental";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import { DENTAL_PROCEDURE_FIELDS, DENTAL_TREATMENT_PLAN_ITEM_FIELDS } from "@/lib/specialty-configs/dentaire";
import { buildSpecialtyPayload } from "@/lib/specialty-validation";
import type { DentalProcedure, DentalTreatmentPlan, DentalTreatmentPlanItem } from "@/types/specialty";

export function DentaireContent({ patientId }: { patientId: number }) {
  const siteSelection = useSiteSelection();
  const chartQuery = useDentalChart(patientId);
  const openConsultationQuery = useOpenConsultation(patientId);
  const createMutation = useCreateDentalChart(patientId);

  if (chartQuery.isLoading) return <TableSkeleton rows={4} columns={2} />;
  if (chartQuery.isError) {
    return <ErrorState message={apiErrorMessage(chartQuery.error)} onRetry={() => chartQuery.refetch()} />;
  }

  if (!chartQuery.data) {
    const siteId = siteSelection.siteId;
    const showSiteSelector = siteSelection.needsManualSelection;
    return (
      <Card>
        <CardHeader>
          <CardTitle>Démarrer le dossier dentaire</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <p className="text-xs text-text-subtle">Aucun dossier dentaire n'existe encore pour ce patient.</p>
          {showSiteSelector && (
            <SiteSelectField
              siteId={siteSelection.siteId}
              onChange={siteSelection.setSiteId}
              options={siteSelection.options}
              isLoading={siteSelection.isLoading}
            />
          )}
          <Button
            disabled={createMutation.isPending || !siteId}
            onClick={() =>
              createMutation.mutate({ site_id: siteId!, consultation_id: openConsultationQuery.data?.id ?? null })
            }
          >
            {createMutation.isPending && <LoaderCircle size={16} className="animate-spin" />}
            Créer le dossier dentaire
          </Button>
          {!showSiteSelector && !siteId && (
            <p className="text-xs text-danger">Aucun site n'est associé à votre compte.</p>
          )}
        </CardContent>
      </Card>
    );
  }

  const chart = chartQuery.data;

  return (
    <div className="space-y-4">
      <Odontogram patientId={patientId} chartId={chart.id} toothStates={chart.tooth_states} />

      <SpecialtyDiagnosisCard consultationId={chart.consultation_id} />

      <ProceduresSection patientId={patientId} chartId={chart.id} procedures={chart.procedures} />

      <TreatmentPlanSection patientId={patientId} chartId={chart.id} plans={chart.treatment_plans} />
    </div>
  );
}

function ProceduresSection({
  patientId,
  chartId,
  procedures,
}: {
  patientId: number;
  chartId: number;
  procedures: DentalProcedure[];
}) {
  const form = useSpecialtyAddForm(DENTAL_PROCEDURE_FIELDS);
  const addMutation = useAddProcedure(patientId, chartId);

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    addMutation.mutate(buildSpecialtyPayload(DENTAL_PROCEDURE_FIELDS, form.values), {
      onSuccess: form.reset,
      onError: (error) => form.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title="Historique des actes"
      items={[...procedures].sort((a, b) => b.performed_at.localeCompare(a.performed_at))}
      emptyLabel="Aucun acte enregistré."
      addButtonLabel="Ajouter un acte"
      renderItem={(procedure) => (
        <div className="flex items-center justify-between gap-4 rounded-md border border-border bg-surface px-4 py-3">
          <div>
            <p className="text-sm font-medium text-text">
              {procedure.act_type}
              {procedure.tooth_fdi && <span className="ml-2 text-xs text-text-subtle">Dent {procedure.tooth_fdi}</span>}
            </p>
            <p className="text-xs text-text-subtle">{formatDate(procedure.performed_at)}</p>
          </div>
        </div>
      )}
      addForm={{
        fields: DENTAL_PROCEDURE_FIELDS,
        values: form.values,
        errors: form.errors,
        onChange: form.onChange,
        onSubmit: handleSubmit,
        isSubmitting: addMutation.isPending,
        globalError: form.globalError,
        submitLabel: "Enregistrer l'acte",
      }}
    />
  );
}

function TreatmentPlanSection({
  patientId,
  chartId,
  plans,
}: {
  patientId: number;
  chartId: number;
  plans: DentalTreatmentPlan[];
}) {
  const createPlanMutation = useCreateTreatmentPlan(patientId, chartId);
  const activePlan = plans.find((p) => p.status === "en_cours") ?? plans[0] ?? null;

  if (!activePlan) {
    return (
      <Card>
        <CardHeader>
          <CardTitle>Plan de traitement</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <p className="text-xs text-text-subtle">Aucun plan de traitement n'a encore été créé.</p>
          <Button disabled={createPlanMutation.isPending} onClick={() => createPlanMutation.mutate()}>
            {createPlanMutation.isPending && <LoaderCircle size={16} className="animate-spin" />}
            Créer un plan de traitement
          </Button>
        </CardContent>
      </Card>
    );
  }

  return <TreatmentPlanItemsSection patientId={patientId} plan={activePlan} />;
}

function TreatmentPlanItemsSection({ patientId, plan }: { patientId: number; plan: DentalTreatmentPlan }) {
  const form = useSpecialtyAddForm(DENTAL_TREATMENT_PLAN_ITEM_FIELDS);
  const addMutation = useAddTreatmentPlanItem(patientId, plan.id);
  const markDoneMutation = useMarkTreatmentPlanItemDone(patientId);

  function handleSubmit() {
    form.setGlobalError(null);
    if (!form.validate()) return;
    addMutation.mutate(buildSpecialtyPayload(DENTAL_TREATMENT_PLAN_ITEM_FIELDS, form.values), {
      onSuccess: form.reset,
      onError: (error) => form.setGlobalError(apiErrorMessage(error)),
    });
  }

  return (
    <SpecialtyHistorySection
      title={`Plan de traitement — ${plan.status === "en_cours" ? "en cours" : plan.status}`}
      items={plan.items}
      emptyLabel="Aucun acte planifié."
      addButtonLabel="Ajouter un acte prévu"
      renderItem={(item: DentalTreatmentPlanItem) => (
        <div className="flex items-center justify-between gap-4 rounded-md border border-border bg-surface px-4 py-3">
          <div>
            <p className="text-sm font-medium text-text">
              {item.act_type}
              {item.tooth_fdi && <span className="ml-2 text-xs text-text-subtle">Dent {item.tooth_fdi}</span>}
            </p>
            {item.planned_at && <p className="text-xs text-text-subtle">Prévu le {formatDate(item.planned_at)}</p>}
          </div>
          <div className="flex items-center gap-2">
            <Badge status={item.status === "realise" ? "success" : item.status === "annule" ? "danger" : "neutral"}>
              {item.status === "realise" ? "Réalisé" : item.status === "annule" ? "Annulé" : "Prévu"}
            </Badge>
            {item.status === "prevu" && (
              <Button
                variant="secondary"
                size="sm"
                disabled={markDoneMutation.isPending}
                onClick={() => markDoneMutation.mutate(item.id)}
              >
                <Check size={14} />
                Marquer réalisé
              </Button>
            )}
          </div>
        </div>
      )}
      addForm={{
        fields: DENTAL_TREATMENT_PLAN_ITEM_FIELDS,
        values: form.values,
        errors: form.errors,
        onChange: form.onChange,
        onSubmit: handleSubmit,
        isSubmitting: addMutation.isPending,
        globalError: form.globalError,
        submitLabel: "Ajouter au plan",
      }}
    />
  );
}
