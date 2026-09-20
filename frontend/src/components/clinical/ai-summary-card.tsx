import { useMutation } from "@tanstack/react-query";
import { LoaderCircle, Sparkles } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { EmptyState } from "@/components/ui/empty-state";
import { Select } from "@/components/ui/select";
import { useGenerateConsultationSummary } from "@/hooks/use-ai-assistance";
import { apiErrorMessage } from "@/lib/api-error";
import { api } from "@/lib/api";
import { textareaClass } from "@/components/clinical/form-controls";
import type { Consultation } from "@/types/api";

type TargetField = "recommendations" | "clinical_exam" | "history_of_illness";

const TARGET_OPTIONS: { value: TargetField; label: string }[] = [
  { value: "recommendations", label: "Recommandations" },
  { value: "clinical_exam", label: "Examen clinique" },
  { value: "history_of_illness", label: "Histoire de la maladie" },
];

export interface AiSummaryCardProps {
  consultationId: number;
  onInserted: (updatedConsultation: Consultation) => void;
}

export function AiSummaryCard({ consultationId, onInserted }: AiSummaryCardProps) {
  // Étape 16 §3 — exigence non négociable, identique au contrat backend
  // (App\Domain\Ai\Contracts\AiProvider / AiAssistanceController::summary) : la génération
  // d'une proposition n'écrit RIEN dans le dossier patient. `draft` n'est qu'un état local en
  // mémoire — un brouillon éditable — tant que le praticien n'a pas cliqué explicitement sur
  // "Insérer dans la consultation" ci-dessous. Aucun useEffect, aucun callback de succès de la
  // génération ne déclenche jamais de PATCH.
  const [draft, setDraft] = useState<string | null>(null);
  const [targetField, setTargetField] = useState<TargetField>("recommendations");
  const [inserted, setInserted] = useState(false);

  const generateMutation = useGenerateConsultationSummary();

  const insertMutation = useMutation({
    mutationFn: async () => {
      const { data } = await api.patch<{ data: Consultation }>(`/consultations/${consultationId}`, {
        [targetField]: (draft ?? "").trim(),
      });
      return data.data;
    },
    onSuccess: (updated) => {
      onInserted(updated);
      setDraft(null);
      setInserted(true);
    },
  });

  function handleGenerate() {
    setInserted(false);
    generateMutation.mutate(consultationId, {
      onSuccess: (response) => {
        setDraft(response.summary);
      },
    });
  }

  function handleClear() {
    // Abandon de la proposition — état purement local, aucun appel réseau.
    setDraft(null);
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Résumé assisté par IA</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <Button
          type="button"
          variant="secondary"
          size="sm"
          onClick={handleGenerate}
          disabled={generateMutation.isPending}
        >
          {generateMutation.isPending ? (
            <LoaderCircle size={14} className="animate-spin" />
          ) : (
            <Sparkles size={14} />
          )}
          Générer un résumé
        </Button>

        {generateMutation.isError && (
          <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
            {apiErrorMessage(generateMutation.error)}
          </p>
        )}

        {draft !== null && (
          <div className="space-y-3 rounded-md border border-accent/40 bg-accent/5 p-4">
            <div className="flex items-center gap-1.5 text-xs font-medium text-accent-light">
              <Sparkles size={13} />
              Généré par IA — à valider avant tout enregistrement
            </div>

            <textarea
              value={draft}
              onChange={(e) => setDraft(e.target.value)}
              rows={5}
              className={textareaClass()}
            />

            <label className="block space-y-1.5">
              <span className="text-xs font-medium text-text-muted">Insérer dans</span>
              <Select value={targetField} onChange={(e) => setTargetField(e.target.value as TargetField)}>
                {TARGET_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </Select>
            </label>

            {insertMutation.isError && (
              <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
                {apiErrorMessage(insertMutation.error)}
              </p>
            )}

            <div className="flex items-center gap-3">
              {/* Action distincte et séparée de "Générer" : seul ce clic explicite déclenche
                  le PATCH qui inscrit le texte dans le dossier de la consultation. */}
              <Button
                type="button"
                variant="primary"
                size="sm"
                onClick={() => insertMutation.mutate()}
                disabled={insertMutation.isPending || draft.trim().length === 0}
              >
                {insertMutation.isPending && <LoaderCircle size={14} className="animate-spin" />}
                Insérer dans la consultation
              </Button>
              <Button type="button" variant="ghost" size="sm" onClick={handleClear}>
                Effacer la proposition
              </Button>
            </div>
          </div>
        )}

        {inserted && draft === null && !insertMutation.isPending && (
          <p className="text-xs text-success">Inséré.</p>
        )}

        {draft === null && !inserted && !generateMutation.isPending && !generateMutation.isError && (
          <EmptyState
            icon={Sparkles}
            title="Aucun résumé généré pour l'instant"
            description="Cliquez sur « Générer un résumé » pour obtenir une proposition à valider — rien n'est enregistré tant que vous ne l'insérez pas explicitement."
            className="py-8"
          />
        )}
      </CardContent>
    </Card>
  );
}
