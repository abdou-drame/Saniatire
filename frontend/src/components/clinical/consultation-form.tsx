import { useMutation, useQueryClient } from "@tanstack/react-query";
import { BedDouble, CheckCircle2, FlaskConical, LoaderCircle, Scan, Syringe } from "lucide-react";
import { useState, type FormEvent } from "react";
import { AdmitPatientDialog } from "@/components/hospitalisation/admit-patient-dialog";
import { PlanSurgicalProcedureDialog } from "@/components/bloc-operatoire/plan-surgical-procedure-dialog";
import { AiSummaryCard } from "@/components/clinical/ai-summary-card";
import { AnomalyAlertCard } from "@/components/clinical/anomaly-alert-card";
import { DiagnosisPicker } from "@/components/clinical/diagnosis-picker";
import { Field, inputClass, textareaClass } from "@/components/clinical/form-controls";
import { PrescribeImagingOrderDialog } from "@/components/imagerie/prescribe-imaging-order-dialog";
import { PrescribeLabOrderDialog } from "@/components/laboratoire/prescribe-lab-order-dialog";
import { SiteSelectField } from "@/components/clinical/site-select-field";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { useAuth } from "@/hooks/use-auth";
import { useHospitalizations } from "@/hooks/use-hospitalizations";
import { useSiteSelection } from "@/hooks/use-site-selection";
import { useSurgicalProcedures } from "@/hooks/use-surgical-procedures";
import { apiErrorMessage } from "@/lib/api-error";
import { api } from "@/lib/api";
import { formatDateTime } from "@/lib/datetime";
import { cn } from "@/lib/utils";
import type { Consultation } from "@/types/api";

interface NumericFieldConfig {
  key: keyof FormState;
  label: string;
  unit?: string;
  min: number;
  max: number;
  step?: string;
}

const NUMERIC_FIELDS: NumericFieldConfig[] = [
  { key: "weight_kg", label: "Poids", unit: "kg", min: 0, max: 500, step: "0.1" },
  { key: "height_cm", label: "Taille", unit: "cm", min: 0, max: 300, step: "0.1" },
  { key: "temperature_c", label: "Température", unit: "°C", min: 25, max: 45, step: "0.1" },
  { key: "blood_pressure_systolic", label: "TA systolique", unit: "mmHg", min: 0, max: 300 },
  { key: "blood_pressure_diastolic", label: "TA diastolique", unit: "mmHg", min: 0, max: 200 },
  { key: "heart_rate", label: "Fréquence cardiaque", unit: "bpm", min: 0, max: 300 },
  { key: "respiratory_rate", label: "Fréquence respiratoire", unit: "/min", min: 0, max: 100 },
  { key: "spo2", label: "SpO2", unit: "%", min: 0, max: 100 },
  { key: "glycemia", label: "Glycémie", unit: "g/L", min: 0, max: 100, step: "0.01" },
  { key: "pain_scale", label: "Échelle de douleur", unit: "/10", min: 0, max: 10 },
];

interface FormState {
  reason: string;
  history_of_illness: string;
  weight_kg: string;
  height_cm: string;
  temperature_c: string;
  blood_pressure_systolic: string;
  blood_pressure_diastolic: string;
  heart_rate: string;
  respiratory_rate: string;
  spo2: string;
  glycemia: string;
  pain_scale: string;
  clinical_exam: string;
  recommendations: string;
  referral: string;
  follow_up_suggested_at: string;
}

function emptyForm(): FormState {
  return {
    reason: "",
    history_of_illness: "",
    weight_kg: "",
    height_cm: "",
    temperature_c: "",
    blood_pressure_systolic: "",
    blood_pressure_diastolic: "",
    heart_rate: "",
    respiratory_rate: "",
    spo2: "",
    glycemia: "",
    pain_scale: "",
    clinical_exam: "",
    recommendations: "",
    referral: "",
    follow_up_suggested_at: "",
  };
}

function fromConsultation(consultation: Consultation): FormState {
  const v = consultation.vitals;
  return {
    reason: consultation.reason ?? "",
    history_of_illness: consultation.history_of_illness ?? "",
    weight_kg: v.weight_kg?.toString() ?? "",
    height_cm: v.height_cm?.toString() ?? "",
    temperature_c: v.temperature_c?.toString() ?? "",
    blood_pressure_systolic: v.blood_pressure_systolic?.toString() ?? "",
    blood_pressure_diastolic: v.blood_pressure_diastolic?.toString() ?? "",
    heart_rate: v.heart_rate?.toString() ?? "",
    respiratory_rate: v.respiratory_rate?.toString() ?? "",
    spo2: v.spo2?.toString() ?? "",
    glycemia: v.glycemia?.toString() ?? "",
    pain_scale: v.pain_scale?.toString() ?? "",
    clinical_exam: consultation.clinical_exam ?? "",
    recommendations: consultation.recommendations ?? "",
    referral: consultation.referral ?? "",
    follow_up_suggested_at: consultation.follow_up_suggested_at ?? "",
  };
}

function buildPayload(form: FormState) {
  const num = (value: string) => (value.trim() === "" ? null : Number(value));
  return {
    reason: form.reason.trim(),
    history_of_illness: form.history_of_illness.trim() || null,
    weight_kg: num(form.weight_kg),
    height_cm: num(form.height_cm),
    temperature_c: num(form.temperature_c),
    blood_pressure_systolic: num(form.blood_pressure_systolic),
    blood_pressure_diastolic: num(form.blood_pressure_diastolic),
    heart_rate: num(form.heart_rate),
    respiratory_rate: num(form.respiratory_rate),
    spo2: num(form.spo2),
    glycemia: num(form.glycemia),
    pain_scale: num(form.pain_scale),
    clinical_exam: form.clinical_exam.trim() || null,
    recommendations: form.recommendations.trim() || null,
    referral: form.referral.trim() || null,
    follow_up_suggested_at: form.follow_up_suggested_at || null,
  };
}

export interface ConsultationFormProps {
  patientId: number;
  practitionerId: number;
  /**
   * Site connu de l'appelant pour créer la consultation (ex. celui du
   * queueEntry). Si `null`/absent, résolu automatiquement (site unique de
   * l'utilisateur) ou via un sélecteur explicite (administrateur, direction
   * — pas de site personnel par conception).
   */
  siteId: number | null;
  appointmentId?: number | null;
  queueEntryId?: number | null;
  existingConsultation?: Consultation | null;
  onConsultationChange?: (consultation: Consultation) => void;
}

export function ConsultationForm({
  patientId,
  practitionerId,
  siteId,
  appointmentId = null,
  queueEntryId = null,
  existingConsultation,
  onConsultationChange,
}: ConsultationFormProps) {
  const queryClient = useQueryClient();
  const { hasPermission } = useAuth();
  const [consultation, setConsultation] = useState<Consultation | null>(existingConsultation ?? null);
  const [form, setForm] = useState<FormState>(
    existingConsultation ? fromConsultation(existingConsultation) : emptyForm(),
  );
  const [fieldErrors, setFieldErrors] = useState<Partial<Record<keyof FormState, string>>>({});
  const [globalError, setGlobalError] = useState<string | null>(null);
  const [justSaved, setJustSaved] = useState(false);
  const [labDialogOpen, setLabDialogOpen] = useState(false);
  const [imagingDialogOpen, setImagingDialogOpen] = useState(false);
  const [admitDialogOpen, setAdmitDialogOpen] = useState(false);
  const [planProcedureDialogOpen, setPlanProcedureDialogOpen] = useState(false);

  const canProposeHospitalisation = hasPermission("hospitalisation.create");
  const canProposeIntervention = hasPermission("bloc_operatoire.create");
  const canGenerateAiSummary = hasPermission("ai.consultation_summary");
  const canViewAnomalies = hasPermission("ai.anomaly_detection");
  const activeHospitalizationsQuery = useHospitalizations({ patientId, status: "en_cours" });
  const surgicalProceduresQuery = useSurgicalProcedures({ patientId });
  const hasActiveHospitalization = (activeHospitalizationsQuery.data?.data.length ?? 0) > 0;
  const hasActiveProcedure = (surgicalProceduresQuery.data?.data ?? []).some(
    (p) => p.status === "planifiee" || p.status === "en_cours",
  );

  const isClosed = consultation?.status === "terminee";

  const siteSelection = useSiteSelection();
  // Un site connu par l'appelant (ex. celui du queueEntry) prime toujours ;
  // sinon on résout automatiquement (site unique) ou via le sélecteur ci-
  // dessous — jamais un blocage silencieux faute de site personnel
  // (administrateur, direction).
  const resolvedNewSiteId = siteId ?? siteSelection.siteId;
  const showSiteSelector = !consultation && siteId === null && siteSelection.needsManualSelection;

  // Une fois la consultation créée, son site_id (backend, jamais nul) fait
  // foi — ne jamais recalculer depuis le queueEntry/l'utilisateur courant,
  // qui peuvent avoir changé ou disparu (ex. rechargement de page perdant
  // le location.state du queueEntry) sans que le site réel de la
  // consultation n'ait bougé.
  const effectiveSiteId = consultation?.site_id ?? resolvedNewSiteId;

  function updateField(key: keyof FormState, value: string) {
    setForm((f) => ({ ...f, [key]: value }));
    setJustSaved(false);
  }

  function validate(): boolean {
    const errors: Partial<Record<keyof FormState, string>> = {};
    if (form.reason.trim().length === 0) {
      errors.reason = "Le motif de consultation est requis.";
    } else if (form.reason.length > 255) {
      errors.reason = "255 caractères maximum.";
    }
    for (const field of NUMERIC_FIELDS) {
      const raw = form[field.key];
      if (raw.trim() === "") continue;
      const n = Number(raw);
      if (Number.isNaN(n)) {
        errors[field.key] = "Valeur numérique attendue.";
      } else if (n < field.min || n > field.max) {
        errors[field.key] = `Doit être entre ${field.min} et ${field.max}.`;
      }
    }
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = buildPayload(form);
      if (consultation) {
        const { data } = await api.patch<{ data: Consultation }>(`/consultations/${consultation.id}`, payload);
        return data.data;
      }
      if (!resolvedNewSiteId) {
        throw new Error("NO_SITE");
      }
      const { data } = await api.post<{ data: Consultation }>("/consultations", {
        ...payload,
        site_id: resolvedNewSiteId,
        patient_id: patientId,
        practitioner_id: practitionerId,
        appointment_id: appointmentId,
      });
      if (queueEntryId) {
        api.patch(`/queue-entries/${queueEntryId}/status`, { status: "en_consultation" }).catch(() => {
          // Best-effort: the consultation itself is the source of truth; a queue
          // status desync here doesn't block the clinical workflow.
        });
      }
      return data.data;
    },
    onSuccess: (data) => {
      setConsultation(data);
      setGlobalError(null);
      setJustSaved(true);
      onConsultationChange?.(data);
      queryClient.invalidateQueries({ queryKey: ["consultations"] });
      queryClient.invalidateQueries({ queryKey: ["queue-entries"] });
      queryClient.invalidateQueries({ queryKey: ["patients", patientId, "timeline"] });
    },
    onError: (error) => {
      if (error instanceof Error && error.message === "NO_SITE") {
        setGlobalError("Aucun site n'est associé à votre compte — impossible de créer la consultation.");
        return;
      }
      setGlobalError(apiErrorMessage(error));
    },
  });

  const closeMutation = useMutation({
    mutationFn: async () => {
      const { data } = await api.post<{ data: Consultation }>(`/consultations/${consultation!.id}/close`);
      return data.data;
    },
    onSuccess: (data) => {
      setConsultation(data);
      onConsultationChange?.(data);
      queryClient.invalidateQueries({ queryKey: ["consultations"] });
      queryClient.invalidateQueries({ queryKey: ["queue-entries"] });
      queryClient.invalidateQueries({ queryKey: ["patients", patientId, "timeline"] });
    },
    onError: (error) => setGlobalError(apiErrorMessage(error)),
  });

  function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setGlobalError(null);
    if (!validate()) return;
    saveMutation.mutate();
  }

  const weight = Number(form.weight_kg);
  const height = Number(form.height_cm);
  const bmi =
    form.weight_kg.trim() !== "" && form.height_cm.trim() !== "" && weight > 0 && height > 0
      ? weight / (height / 100) ** 2
      : null;

  return (
    <div className="space-y-4">
      {isClosed && consultation?.closed_at && (
        <div className="flex items-center gap-2 rounded-md border border-success/30 bg-success/10 px-4 py-3 text-sm text-success">
          <CheckCircle2 size={16} />
          Consultation clôturée le {formatDateTime(consultation.closed_at)}.
        </div>
      )}

      <form onSubmit={handleSubmit}>
        <Card>
          <CardHeader>
            <CardTitle>Motif et anamnèse</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <Field label="Motif de consultation" error={fieldErrors.reason} required>
              <input
                type="text"
                disabled={isClosed}
                value={form.reason}
                onChange={(e) => updateField("reason", e.target.value)}
                className={inputClass(Boolean(fieldErrors.reason))}
                placeholder="Ex. douleur thoracique depuis 2 jours"
              />
            </Field>
            <Field label="Histoire de la maladie">
              <textarea
                disabled={isClosed}
                value={form.history_of_illness}
                onChange={(e) => updateField("history_of_illness", e.target.value)}
                rows={3}
                className={textareaClass()}
              />
            </Field>
          </CardContent>
        </Card>

        <Card className="mt-4">
          <CardHeader>
            <CardTitle>Constantes cliniques</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
              {NUMERIC_FIELDS.map((field) => (
                <Field key={field.key} label={field.label} error={fieldErrors[field.key]}>
                  <div className="relative">
                    <input
                      type="number"
                      disabled={isClosed}
                      step={field.step ?? "1"}
                      value={form[field.key]}
                      onChange={(e) => updateField(field.key, e.target.value)}
                      className={cn(inputClass(Boolean(fieldErrors[field.key])), field.unit && "pr-10")}
                    />
                    {field.unit && (
                      <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-text-subtle">
                        {field.unit}
                      </span>
                    )}
                  </div>
                </Field>
              ))}
              <div className="flex flex-col justify-center rounded-md border border-dashed border-border px-3 py-2">
                <span className="text-xs text-text-subtle">IMC</span>
                <span className="font-tabular text-sm font-medium text-text">
                  {bmi ? `${bmi.toFixed(1)} kg/m²` : "—"}
                </span>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="mt-4">
          <CardHeader>
            <CardTitle>Examen clinique &amp; orientation</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <Field label="Examen clinique">
              <textarea
                disabled={isClosed}
                value={form.clinical_exam}
                onChange={(e) => updateField("clinical_exam", e.target.value)}
                rows={3}
                className={textareaClass()}
              />
            </Field>
            <Field label="Recommandations">
              <textarea
                disabled={isClosed}
                value={form.recommendations}
                onChange={(e) => updateField("recommendations", e.target.value)}
                rows={2}
                className={textareaClass()}
              />
            </Field>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Field label="Orientation">
                <input
                  type="text"
                  disabled={isClosed}
                  value={form.referral}
                  onChange={(e) => updateField("referral", e.target.value)}
                  className={inputClass(false)}
                  placeholder="Ex. cardiologie"
                />
              </Field>
              <Field label="RDV de contrôle proposé">
                <input
                  type="date"
                  disabled={isClosed}
                  value={form.follow_up_suggested_at}
                  onChange={(e) => updateField("follow_up_suggested_at", e.target.value)}
                  className={inputClass(false)}
                />
              </Field>
            </div>
          </CardContent>
        </Card>

        {showSiteSelector && (
          <div className="mt-4">
            <SiteSelectField
              siteId={siteSelection.siteId}
              onChange={siteSelection.setSiteId}
              options={siteSelection.options}
              isLoading={siteSelection.isLoading}
            />
          </div>
        )}

        {globalError && (
          <p className="mt-4 rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
            {globalError}
          </p>
        )}

        {!isClosed && (
          <div className="mt-4 flex items-center gap-3">
            <Button type="submit" disabled={saveMutation.isPending}>
              {saveMutation.isPending && <LoaderCircle size={16} className="animate-spin" />}
              {consultation ? "Enregistrer les modifications" : "Créer la consultation"}
            </Button>
            {justSaved && !saveMutation.isPending && (
              <span className="text-xs text-success">Enregistré.</span>
            )}
          </div>
        )}
      </form>

      <Card>
        <CardHeader>
          <CardTitle>Diagnostic (CIM)</CardTitle>
        </CardHeader>
        <CardContent>
          {consultation ? (
            <DiagnosisPicker consultationId={consultation.id} diagnoses={consultation.diagnoses} />
          ) : (
            <p className="text-xs text-text-subtle">
              Créez d'abord la consultation pour pouvoir y attacher des diagnostics.
            </p>
          )}
        </CardContent>
      </Card>

      {consultation && canGenerateAiSummary && (
        <AiSummaryCard
          consultationId={consultation.id}
          onInserted={(updated) => {
            // Resynchronise l'état local (state + form) après une insertion IA explicite,
            // exactement comme le onSuccess de saveMutation ci-dessus — sinon le textarea du
            // champ ciblé afficherait encore l'ancienne valeur alors que la consultation en
            // base a changé.
            setConsultation(updated);
            setForm(fromConsultation(updated));
            onConsultationChange?.(updated);
            queryClient.invalidateQueries({ queryKey: ["consultations"] });
            queryClient.invalidateQueries({ queryKey: ["queue-entries"] });
            queryClient.invalidateQueries({ queryKey: ["patients", patientId, "timeline"] });
          }}
        />
      )}

      {consultation && canViewAnomalies && <AnomalyAlertCard consultationId={consultation.id} />}

      <Card>
        <CardHeader>
          <CardTitle>Laboratoire</CardTitle>
        </CardHeader>
        <CardContent>
          {consultation ? (
            <>
              <Button variant="secondary" size="sm" onClick={() => setLabDialogOpen(true)}>
                <FlaskConical size={14} />
                Prescrire une analyse
              </Button>
              <PrescribeLabOrderDialog
                open={labDialogOpen}
                onOpenChange={setLabDialogOpen}
                patientId={patientId}
                fixedSiteId={effectiveSiteId}
                practitionerId={practitionerId}
                consultationId={consultation.id}
              />
            </>
          ) : (
            <p className="text-xs text-text-subtle">
              Créez d'abord la consultation pour pouvoir y prescrire une analyse.
            </p>
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Imagerie</CardTitle>
        </CardHeader>
        <CardContent>
          {consultation ? (
            <>
              <Button variant="secondary" size="sm" onClick={() => setImagingDialogOpen(true)}>
                <Scan size={14} />
                Prescrire un examen d'imagerie
              </Button>
              <PrescribeImagingOrderDialog
                open={imagingDialogOpen}
                onOpenChange={setImagingDialogOpen}
                patientId={patientId}
                fixedSiteId={effectiveSiteId}
                practitionerId={practitionerId}
                consultationId={consultation.id}
              />
            </>
          ) : (
            <p className="text-xs text-text-subtle">
              Créez d'abord la consultation pour pouvoir y prescrire un examen d'imagerie.
            </p>
          )}
        </CardContent>
      </Card>

      {canProposeHospitalisation && (
        <Card>
          <CardHeader>
            <CardTitle>Hospitalisation</CardTitle>
          </CardHeader>
          <CardContent>
            {consultation ? (
              hasActiveHospitalization ? (
                <p className="text-xs text-text-subtle">Ce patient est déjà hospitalisé.</p>
              ) : (
                <>
                  <Button variant="secondary" size="sm" onClick={() => setAdmitDialogOpen(true)}>
                    <BedDouble size={14} />
                    Proposer une hospitalisation
                  </Button>
                  <AdmitPatientDialog
                    open={admitDialogOpen}
                    onOpenChange={setAdmitDialogOpen}
                    patientId={patientId}
                    fixedSiteId={effectiveSiteId}
                    attendingPhysicianId={practitionerId}
                    onAdmitted={() => {
                      activeHospitalizationsQuery.refetch();
                      queryClient.invalidateQueries({ queryKey: ["patients", patientId, "timeline"] });
                    }}
                  />
                </>
              )
            ) : (
              <p className="text-xs text-text-subtle">
                Créez d'abord la consultation pour pouvoir proposer une hospitalisation.
              </p>
            )}
          </CardContent>
        </Card>
      )}

      {canProposeIntervention && (
        <Card>
          <CardHeader>
            <CardTitle>Bloc opératoire</CardTitle>
          </CardHeader>
          <CardContent>
            {consultation ? (
              hasActiveProcedure ? (
                <p className="text-xs text-text-subtle">Une intervention est déjà planifiée ou en cours pour ce patient.</p>
              ) : (
                <>
                  <Button variant="secondary" size="sm" onClick={() => setPlanProcedureDialogOpen(true)}>
                    <Syringe size={14} />
                    Proposer une intervention chirurgicale
                  </Button>
                  <PlanSurgicalProcedureDialog
                    open={planProcedureDialogOpen}
                    onOpenChange={setPlanProcedureDialogOpen}
                    patientId={patientId}
                    fixedSiteId={effectiveSiteId}
                    onPlanned={() => {
                      surgicalProceduresQuery.refetch();
                      queryClient.invalidateQueries({ queryKey: ["patients", patientId, "timeline"] });
                    }}
                  />
                </>
              )
            ) : (
              <p className="text-xs text-text-subtle">
                Créez d'abord la consultation pour pouvoir proposer une intervention chirurgicale.
              </p>
            )}
          </CardContent>
        </Card>
      )}

      {consultation && !isClosed && (
        <div className="flex items-center gap-3">
          <Button variant="danger" onClick={() => closeMutation.mutate()} disabled={closeMutation.isPending}>
            {closeMutation.isPending && <LoaderCircle size={16} className="animate-spin" />}
            Clôturer la consultation
          </Button>
        </div>
      )}
    </div>
  );
}

