import { LoaderCircle } from "lucide-react";
import { useState, type FormEvent } from "react";
import { useNavigate } from "react-router-dom";
import { Field, inputClass, textareaClass } from "@/components/clinical/form-controls";
import { Button } from "@/components/ui/button";
import { useCloseTeleconsultation } from "@/hooks/use-teleconsultations";
import { apiErrorMessage } from "@/lib/api-error";
import type { Consultation } from "@/types/api";

export interface CloseTeleconsultationFormProps {
  teleconsultationId: number;
  onClosed?: (consultation: Consultation) => void;
  onCancel?: () => void;
}

interface FormState {
  reason: string;
  history_of_illness: string;
  clinical_exam: string;
  recommendations: string;
  referral: string;
  follow_up_suggested_at: string;
}

function emptyForm(): FormState {
  return {
    reason: "",
    history_of_illness: "",
    clinical_exam: "",
    recommendations: "",
    referral: "",
    follow_up_suggested_at: "",
  };
}

/**
 * Formulaire de clôture d'une téléconsultation — reprend exactement les
 * libellés de ConsultationForm (components/clinical/consultation-form.tsx)
 * mais ne route jamais par POST /consultations : le backend crée et clôture
 * la Consultation en un seul appel atomique
 * (TeleconsultationController::close, voir useCloseTeleconsultation dans
 * use-teleconsultations.ts), qui renvoie cette Consultation — pas la
 * Teleconsultation elle-même. Succès → redirection vers /patients/{id} : la
 * timeline affichera automatiquement la nouvelle consultation, déjà
 * clôturée, sans marque distinctive (fait backend confirmé par le plan, pas
 * une omission frontend).
 *
 * Ne reçoit pas patientId en prop (contrairement à une première ébauche) :
 * la redirection utilise consultation.patient_id retourné par l'appel lui
 * -même, donc aucune prop supplémentaire n'était nécessaire — et sous
 * noUnusedParameters (tsconfig.app.json) une prop reçue mais jamais lue
 * aurait échoué à la compilation.
 */
export function CloseTeleconsultationForm({ teleconsultationId, onClosed, onCancel }: CloseTeleconsultationFormProps) {
  const navigate = useNavigate();
  const closeTeleconsultation = useCloseTeleconsultation();
  const [form, setForm] = useState<FormState>(emptyForm());
  const [fieldErrors, setFieldErrors] = useState<Partial<Record<keyof FormState, string>>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);

  function updateField(key: keyof FormState, value: string) {
    setForm((f) => ({ ...f, [key]: value }));
  }

  function validate(): boolean {
    const errors: Partial<Record<keyof FormState, string>> = {};
    if (form.reason.trim().length === 0) {
      errors.reason = "Le motif de consultation est requis.";
    } else if (form.reason.length > 255) {
      errors.reason = "255 caractères maximum.";
    }
    if (form.referral.length > 255) {
      errors.referral = "255 caractères maximum.";
    }
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitError(null);
    if (!validate()) return;
    try {
      const consultation = await closeTeleconsultation.mutateAsync({
        id: teleconsultationId,
        reason: form.reason.trim(),
        history_of_illness: form.history_of_illness.trim() || null,
        clinical_exam: form.clinical_exam.trim() || null,
        recommendations: form.recommendations.trim() || null,
        referral: form.referral.trim() || null,
        follow_up_suggested_at: form.follow_up_suggested_at || null,
      });
      onClosed?.(consultation);
      navigate(`/patients/${consultation.patient_id}`);
    } catch (error) {
      // Ex. si la téléconsultation a déjà été clôturée/annulée entre-temps :
      // "Cette téléconsultation est déjà clôturée ou annulée." affiché
      // verbatim, jamais reformulé.
      setSubmitError(apiErrorMessage(error));
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4 rounded-md border border-border bg-surface p-4">
      <Field label="Motif de consultation" error={fieldErrors.reason} required>
        <input
          type="text"
          value={form.reason}
          onChange={(e) => updateField("reason", e.target.value)}
          className={inputClass(Boolean(fieldErrors.reason))}
          placeholder="Ex. suivi post-opératoire à distance"
        />
      </Field>
      <Field label="Histoire de la maladie">
        <textarea
          value={form.history_of_illness}
          onChange={(e) => updateField("history_of_illness", e.target.value)}
          rows={3}
          className={textareaClass()}
        />
      </Field>
      <Field label="Examen clinique">
        <textarea
          value={form.clinical_exam}
          onChange={(e) => updateField("clinical_exam", e.target.value)}
          rows={3}
          className={textareaClass()}
        />
      </Field>
      <Field label="Recommandations">
        <textarea
          value={form.recommendations}
          onChange={(e) => updateField("recommendations", e.target.value)}
          rows={2}
          className={textareaClass()}
        />
      </Field>
      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <Field label="Orientation" error={fieldErrors.referral}>
          <input
            type="text"
            value={form.referral}
            onChange={(e) => updateField("referral", e.target.value)}
            className={inputClass(Boolean(fieldErrors.referral))}
            placeholder="Ex. cardiologie"
          />
        </Field>
        <Field label="RDV de contrôle proposé">
          <input
            type="date"
            value={form.follow_up_suggested_at}
            onChange={(e) => updateField("follow_up_suggested_at", e.target.value)}
            className={inputClass(false)}
          />
        </Field>
      </div>

      {submitError && (
        <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{submitError}</p>
      )}

      <div className="flex items-center justify-end gap-2">
        {onCancel && (
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={onCancel}
            disabled={closeTeleconsultation.isPending}
          >
            Annuler
          </Button>
        )}
        <Button type="submit" size="sm" disabled={closeTeleconsultation.isPending}>
          {closeTeleconsultation.isPending && <LoaderCircle size={14} className="animate-spin" />}
          Clôturer
        </Button>
      </div>
    </form>
  );
}
