import { useQuery, useQueryClient } from "@tanstack/react-query";
import { AlertTriangle, ArrowLeft, Droplet, FlaskConical, Scan, Stethoscope } from "lucide-react";
import { useState } from "react";
import { useLocation, useNavigate, useParams } from "react-router-dom";
import { ConsultationForm } from "@/components/clinical/consultation-form";
import { PrescribeImagingOrderDialog } from "@/components/imagerie/prescribe-imaging-order-dialog";
import { PrescribeLabOrderDialog } from "@/components/laboratoire/prescribe-lab-order-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { Skeleton } from "@/components/ui/loading-state";
import { useAuth } from "@/hooks/use-auth";
import { api } from "@/lib/api";
import { apiErrorMessage } from "@/lib/api-error";
import { age } from "@/lib/datetime";
import { PatientHospitalizationPanel } from "@/pages/patients/patient-hospitalization-panel";
import { PatientTimeline } from "@/pages/patients/patient-timeline";
import { PatientVitalsPanel } from "@/pages/patients/patient-vitals-panel";
import { SPECIALTY_UI_REGISTRY } from "@/lib/specialty-registry";
import type { Consultation, Paginated, Patient, QueueEntry, TimelineEvent } from "@/types/api";

export function PatientDetailPage() {
  const { id } = useParams<{ id: string }>();
  const patientId = Number(id);
  const navigate = useNavigate();
  const location = useLocation();
  const { user, hasRole, hasPermission } = useAuth();
  const queryClient = useQueryClient();
  const [starting, setStarting] = useState(false);
  const [labDialogOpen, setLabDialogOpen] = useState(false);
  const [imagingDialogOpen, setImagingDialogOpen] = useState(false);

  const queueEntry = (location.state as { queueEntry?: QueueEntry } | null)?.queueEntry ?? null;

  const patientQuery = useQuery({
    queryKey: ["patients", patientId],
    queryFn: async () => {
      const { data } = await api.get<{ data: Patient }>(`/patients/${patientId}`);
      return data.data;
    },
    enabled: Number.isFinite(patientId),
  });

  const timelineQuery = useQuery({
    queryKey: ["patients", patientId, "timeline"],
    queryFn: async () => {
      const { data } = await api.get<{ data: TimelineEvent[] }>(`/patients/${patientId}/timeline`);
      return data.data;
    },
    enabled: Number.isFinite(patientId),
  });

  const openConsultationQuery = useQuery({
    queryKey: ["consultations", "open", patientId, user?.id],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Consultation>>("/consultations", {
        params: { patient_id: patientId, practitioner_id: user!.id, status: "en_cours" },
      });
      return data.data[0] ?? null;
    },
    enabled: Boolean(user) && hasRole("medecin") && Number.isFinite(patientId),
  });

  if (!Number.isFinite(patientId)) {
    return <ErrorState message="Identifiant patient invalide." />;
  }

  const visibleSpecialties = Object.values(SPECIALTY_UI_REGISTRY).filter((meta) =>
    hasPermission(meta.viewPermission),
  );

  return (
    <div className="space-y-6">
      <button
        onClick={() => navigate(-1)}
        className="flex items-center gap-1.5 text-xs text-text-muted hover:text-text"
      >
        <ArrowLeft size={14} />
        Retour
      </button>

      {patientQuery.isLoading && (
        <Card className="p-6">
          <Skeleton className="h-6 w-48" />
          <Skeleton className="mt-3 h-4 w-64" />
        </Card>
      )}

      {patientQuery.isError && (
        <ErrorState message={apiErrorMessage(patientQuery.error)} onRetry={() => patientQuery.refetch()} />
      )}

      {patientQuery.data && (
        <Card className="p-6">
          <div className="flex flex-wrap items-start justify-between gap-4">
            <div>
              <h1 className="font-heading text-xl font-semibold text-text">
                {patientQuery.data.first_name} {patientQuery.data.last_name}
              </h1>
              <p className="mt-1 font-tabular text-xs text-text-subtle">
                {patientQuery.data.patient_number} · {patientQuery.data.sex === "M" ? "Homme" : "Femme"} ·{" "}
                {age(patientQuery.data.birth_date)} ans
              </p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              {patientQuery.data.medical_info?.blood_group && (
                <Badge status="accent" dot={false}>
                  <Droplet size={11} />
                  {patientQuery.data.medical_info.blood_group}
                </Badge>
              )}
              {(patientQuery.data.allergies ?? []).map((allergy) => (
                <Badge key={allergy.id} status="danger">
                  <AlertTriangle size={11} />
                  {allergy.allergen}
                </Badge>
              ))}
            </div>
          </div>
        </Card>
      )}

      {patientQuery.data && visibleSpecialties.length > 0 && (
        <div className="flex flex-wrap gap-2">
          {visibleSpecialties.map((meta) => {
            const Icon = meta.icon;
            return (
              <Button
                key={meta.type}
                variant="secondary"
                size="sm"
                onClick={() => navigate(meta.route(patientId))}
              >
                <Icon size={14} />
                {meta.label}
              </Button>
            );
          })}
        </div>
      )}

      {patientQuery.data && (
        <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
          <Card className="lg:col-span-2">
            <CardHeader>
              <CardTitle>Dossier — historique</CardTitle>
            </CardHeader>
            <CardContent>
              {timelineQuery.isLoading && (
                <div className="space-y-3">
                  <Skeleton className="h-10 w-full" />
                  <Skeleton className="h-10 w-full" />
                  <Skeleton className="h-10 w-full" />
                </div>
              )}
              {timelineQuery.isError && (
                <ErrorState
                  message={apiErrorMessage(timelineQuery.error)}
                  onRetry={() => timelineQuery.refetch()}
                />
              )}
              {timelineQuery.data && <PatientTimeline events={timelineQuery.data} />}
            </CardContent>
          </Card>

          {timelineQuery.data && <PatientVitalsPanel events={timelineQuery.data} />}
        </div>
      )}

      {patientQuery.data && <PatientHospitalizationPanel patientId={patientId} />}

      {patientQuery.data && hasRole("medecin") && (
        <div className="space-y-4">
          <div className="flex flex-wrap justify-end gap-2">
            <Button variant="secondary" size="sm" onClick={() => setLabDialogOpen(true)}>
              <FlaskConical size={14} />
              Prescrire une analyse
            </Button>
            <Button variant="secondary" size="sm" onClick={() => setImagingDialogOpen(true)}>
              <Scan size={14} />
              Prescrire un examen d'imagerie
            </Button>
          </div>
          {openConsultationQuery.data ? (
            <ConsultationForm
              patientId={patientId}
              practitionerId={user!.id}
              siteId={queueEntry?.site_id ?? user!.sites[0]?.id ?? null}
              queueEntryId={queueEntry?.status === "en_consultation" ? null : (queueEntry?.id ?? null)}
              existingConsultation={openConsultationQuery.data}
            />
          ) : starting ? (
            <ConsultationForm
              patientId={patientId}
              practitionerId={user!.id}
              siteId={queueEntry?.site_id ?? user!.sites[0]?.id ?? null}
              queueEntryId={queueEntry?.id ?? null}
            />
          ) : (
            <Card className="p-5">
              <div className="flex items-center justify-between gap-4">
                <div>
                  <p className="text-sm font-medium text-text">Nouvelle consultation</p>
                  <p className="text-xs text-text-muted">
                    Démarrer une consultation pour ce patient.
                  </p>
                </div>
                <Button onClick={() => setStarting(true)}>
                  <Stethoscope size={15} />
                  Démarrer la consultation
                </Button>
              </div>
            </Card>
          )}
          <PrescribeLabOrderDialog
            open={labDialogOpen}
            onOpenChange={setLabDialogOpen}
            patientId={patientId}
            siteId={queueEntry?.site_id ?? user!.sites[0]?.id ?? null}
            practitionerId={user!.id}
            consultationId={null}
            onCreated={() => queryClient.invalidateQueries({ queryKey: ["patients", patientId, "timeline"] })}
          />
          <PrescribeImagingOrderDialog
            open={imagingDialogOpen}
            onOpenChange={setImagingDialogOpen}
            patientId={patientId}
            siteId={queueEntry?.site_id ?? user!.sites[0]?.id ?? null}
            practitionerId={user!.id}
            consultationId={null}
            onCreated={() => queryClient.invalidateQueries({ queryKey: ["patients", patientId, "timeline"] })}
          />
        </div>
      )}
    </div>
  );
}
