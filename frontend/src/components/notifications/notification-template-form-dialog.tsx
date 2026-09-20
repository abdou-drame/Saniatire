import { useEffect, useMemo, useRef, useState, type FormEvent } from "react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { FieldError, Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { useCreateNotificationTemplate, useUpdateNotificationTemplate } from "@/hooks/use-notification-templates";
import { apiErrorMessage } from "@/lib/api-error";
import {
  NOTIFICATION_CANAL_LABEL,
  NOTIFICATION_CANAL_WARNING,
} from "@/pages/notifications/notification-channel-status";
import type { NotificationCanal, NotificationTemplate } from "@/types/api";

export interface NotificationTemplateFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  template?: NotificationTemplate | null;
}

const CANAL_OPTIONS: NotificationCanal[] = ["email", "sms", "whatsapp", "push"];

/**
 * Types d'événements utilisés par les templates par défaut fournis par
 * NotificationTemplateSeeder — de simples suggestions dans un <datalist>,
 * jamais une contrainte : une structure peut créer un type_evenement libre
 * (validation serveur = string|max:100, pas un enum).
 */
const SUGGESTED_EVENT_TYPES = [
  "rdv_cree",
  "rdv_modifie",
  "rdv_annule",
  "rdv_rappel",
  "conge_valide",
  "resultat_disponible",
  "facture_echeance",
  "resultat_disponible_prescripteur",
  "referencement_accepte",
  "referencement_refuse",
  "teleconsultation_rappel",
  "patient_portal_activation",
  "patient_password_reset",
  "enquete_satisfaction",
];

/**
 * Variables utilisées par les templates par défaut (NotificationTemplateSeeder)
 * — une référence indicative, jamais un catalogue officiel : aucun endpoint
 * backend ne liste "les variables disponibles pour un type_evenement", et une
 * structure peut définir ses propres variables libres dans ses templates.
 */
const COMMON_VARIABLES = [
  "patient_nom",
  "date_rdv",
  "praticien_nom",
  "user_nom",
  "date_debut",
  "date_fin",
  "facture_numero",
  "montant_restant",
  "date_echeance",
  "structure_destination_nom",
  "lien_activation",
  "lien_reinitialisation",
  "service",
];

/** Extrait tous les tokens {nom_variable} présents dans un texte, sans doublons. */
function extractTokens(text: string): string[] {
  const tokens: string[] = [];
  const regex = /\{(\w+)\}/g;
  let match: RegExpExecArray | null;
  while ((match = regex.exec(text)) !== null) {
    if (!tokens.includes(match[1])) tokens.push(match[1]);
  }
  return tokens;
}

/**
 * Reproduit EXACTEMENT la logique de NotificationTemplate::render() côté
 * backend : remplace {token} par sa valeur si fournie, sinon laisse {token}
 * tel quel. Calcul 100% local pour la prévisualisation — jamais envoyé au
 * serveur, jamais de donnée patient/rendez-vous réelle en jeu (uniquement
 * des valeurs d'exemple saisies par l'utilisateur lui-même).
 */
function substitute(text: string, values: Record<string, string>): string {
  return text.replace(/\{(\w+)\}/g, (full, token: string) => {
    const value = values[token];
    return value ? value : full;
  });
}

export function NotificationTemplateFormDialog({ open, onOpenChange, template }: NotificationTemplateFormDialogProps) {
  const isEditing = Boolean(template);
  const createTemplate = useCreateNotificationTemplate();
  const updateTemplate = useUpdateNotificationTemplate();

  const [typeEvenement, setTypeEvenement] = useState("");
  const [canal, setCanal] = useState<NotificationCanal>("email");
  const [sujet, setSujet] = useState("");
  const [contenu, setContenu] = useState("");
  const [actif, setActif] = useState(true);
  const [exampleValues, setExampleValues] = useState<Record<string, string>>({});
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);

  const contenuRef = useRef<HTMLTextAreaElement>(null);

  useEffect(() => {
    if (!open) return;
    setTypeEvenement(template?.type_evenement ?? "");
    setCanal(template?.canal ?? "email");
    setSujet(template?.sujet ?? "");
    setContenu(template?.contenu ?? "");
    setActif(template?.actif ?? true);
    setExampleValues({});
    setFieldErrors({});
    setSubmitError(null);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, template]);

  const tokens = useMemo(
    () => Array.from(new Set([...extractTokens(sujet), ...extractTokens(contenu)])),
    [sujet, contenu],
  );

  const canalWarning = NOTIFICATION_CANAL_WARNING[canal];

  function insertVariable(name: string) {
    const token = `{${name}}`;
    const textarea = contenuRef.current;
    if (!textarea) {
      setContenu((c) => c + token);
      return;
    }
    const start = textarea.selectionStart ?? contenu.length;
    const end = textarea.selectionEnd ?? contenu.length;
    const next = contenu.slice(0, start) + token + contenu.slice(end);
    setContenu(next);
    requestAnimationFrame(() => {
      textarea.focus();
      const cursor = start + token.length;
      textarea.setSelectionRange(cursor, cursor);
    });
  }

  function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!typeEvenement.trim()) errors.typeEvenement = "Le type d'événement est obligatoire.";
    if (!contenu.trim()) errors.contenu = "Le contenu est obligatoire.";
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitError(null);
    if (!validate()) return;

    const input = {
      type_evenement: typeEvenement.trim(),
      canal,
      sujet: sujet.trim() ? sujet.trim() : null,
      contenu,
      actif,
    };

    try {
      if (isEditing && template) {
        await updateTemplate.mutateAsync({ id: template.id, ...input });
      } else {
        await createTemplate.mutateAsync(input);
      }
      onOpenChange(false);
    } catch (error) {
      setSubmitError(apiErrorMessage(error));
    }
  }

  const isPending = createTemplate.isPending || updateTemplate.isPending;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{isEditing ? "Modifier le template" : "Nouveau template de notification"}</DialogTitle>
          <DialogDescription>
            Un template définit le sujet et le contenu d'une notification envoyée automatiquement pour un événement
            donné, sur un canal donné.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit} className="space-y-4">
          {submitError && (
            <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">
              {submitError}
            </p>
          )}

          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label htmlFor="template-type-evenement">Type d'événement</Label>
              <Input
                id="template-type-evenement"
                list="notification-event-type-suggestions"
                value={typeEvenement}
                onChange={(e) => setTypeEvenement(e.target.value)}
                placeholder="ex. rdv_rappel"
              />
              <datalist id="notification-event-type-suggestions">
                {SUGGESTED_EVENT_TYPES.map((type) => (
                  <option key={type} value={type} />
                ))}
              </datalist>
              <FieldError>{fieldErrors.typeEvenement}</FieldError>
              <p className="mt-1 text-xs text-text-subtle">
                Libre — les valeurs suggérées correspondent aux événements déjà utilisés par l'application, une
                structure peut définir les siens.
              </p>
            </div>

            <div>
              <Label htmlFor="template-canal">Canal</Label>
              <Select id="template-canal" value={canal} onChange={(e) => setCanal(e.target.value as NotificationCanal)}>
                {CANAL_OPTIONS.map((c) => (
                  <option key={c} value={c}>
                    {NOTIFICATION_CANAL_LABEL[c]}
                  </option>
                ))}
              </Select>
            </div>
          </div>

          {canalWarning && (
            <p className="rounded-md border border-warning/30 bg-warning/5 px-3 py-2 text-xs text-warning">
              ⚠ {canalWarning}
            </p>
          )}

          <div>
            <Label htmlFor="template-sujet">Sujet (optionnel)</Label>
            <Input id="template-sujet" value={sujet} onChange={(e) => setSujet(e.target.value)} />
          </div>

          <div>
            <Label htmlFor="template-contenu">Contenu</Label>
            <Textarea
              id="template-contenu"
              ref={contenuRef}
              rows={6}
              value={contenu}
              onChange={(e) => setContenu(e.target.value)}
            />
            <FieldError>{fieldErrors.contenu}</FieldError>
          </div>

          <div>
            <Label>Variables courantes</Label>
            <p className="mb-1.5 text-xs text-text-subtle">
              Variables utilisées par les templates par défaut de l'application, à titre indicatif — aucune liste
              officielle des variables disponibles n'existe côté serveur pour un type d'événement personnalisé.
              Cliquer sur une variable l'insère dans le contenu.
            </p>
            <div className="flex flex-wrap gap-1.5">
              {COMMON_VARIABLES.map((name) => (
                <button
                  key={name}
                  type="button"
                  onClick={() => insertVariable(name)}
                  className="rounded-full border border-border bg-surface-hover px-2.5 py-0.5 text-xs font-medium text-text-muted hover:border-border-strong hover:text-text"
                >
                  {`{${name}}`}
                </button>
              ))}
            </div>
          </div>

          <label className="flex items-center gap-2 text-sm font-normal text-text">
            <input type="checkbox" checked={actif} onChange={(e) => setActif(e.target.checked)} />
            Actif
          </label>

          {tokens.length > 0 && (
            <div className="space-y-3 rounded-md border border-dashed border-border p-3">
              <div>
                <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">
                  Aperçu avec valeurs d'exemple
                </p>
                <p className="mt-0.5 text-xs text-text-subtle">
                  Calcul local, jamais envoyé au serveur — le rendu réel utilisera les vraies données du
                  patient/rendez-vous au moment de l'envoi.
                </p>
              </div>

              <div className="grid grid-cols-2 gap-2">
                {tokens.map((token) => (
                  <div key={token}>
                    <Label htmlFor={`template-example-${token}`}>{`{${token}}`}</Label>
                    <Input
                      id={`template-example-${token}`}
                      value={exampleValues[token] ?? ""}
                      onChange={(e) => setExampleValues((prev) => ({ ...prev, [token]: e.target.value }))}
                      placeholder="valeur d'exemple"
                    />
                  </div>
                ))}
              </div>

              {sujet.trim() && (
                <div>
                  <p className="text-xs font-medium text-text-subtle">Sujet rendu</p>
                  <p className="text-sm text-text">{substitute(sujet, exampleValues)}</p>
                </div>
              )}
              <div>
                <p className="text-xs font-medium text-text-subtle">Contenu rendu</p>
                <p className="whitespace-pre-wrap text-sm text-text">{substitute(contenu, exampleValues)}</p>
              </div>
            </div>
          )}

          <DialogFooter>
            <Button type="button" variant="secondary" onClick={() => onOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={isPending}>
              {isPending ? "Enregistrement..." : isEditing ? "Enregistrer" : "Créer le template"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
