import { LoaderCircle, X } from "lucide-react";
import { useState } from "react";
import { SpecialtyForm } from "@/components/clinical/specialty-form";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { useUpdateToothState } from "@/hooks/specialties/use-dental";
import { apiErrorMessage } from "@/lib/api-error";
import { buildSpecialtyPayload, validateSpecialtyFields } from "@/lib/specialty-validation";
import { TOOTH_STATE_FIELDS, TOOTH_STATUS_STYLES } from "@/lib/specialty-configs/dentaire";
import { cn } from "@/lib/utils";
import type { SpecialtyFieldErrors, SpecialtyFieldValues } from "@/types/specialty-config";
import type { DentalToothState } from "@/types/specialty";

const UPPER_ROW = ["18", "17", "16", "15", "14", "13", "12", "11", "21", "22", "23", "24", "25", "26", "27", "28"];
const LOWER_ROW = ["48", "47", "46", "45", "44", "43", "42", "41", "31", "32", "33", "34", "35", "36", "37", "38"];

/**
 * Bespoke visual odontogram — the one genuinely special case in this
 * architecture that doesn't fit the declarative SpecialtyField model
 * (a grid of individually clickable, individually colored teeth). Still
 * persists through the same generic hook/validation building blocks as
 * every other specialty screen.
 */
export function Odontogram({
  patientId,
  chartId,
  toothStates,
}: {
  patientId: number;
  chartId: number;
  toothStates: DentalToothState[];
}) {
  const [selectedFdi, setSelectedFdi] = useState<string | null>(null);
  const byFdi = new Map(toothStates.map((t) => [t.tooth_fdi, t]));

  return (
    <Card>
      <CardHeader>
        <CardTitle>Odontogramme</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="space-y-1.5 overflow-x-auto">
          <ToothRow codes={UPPER_ROW} byFdi={byFdi} selectedFdi={selectedFdi} onSelect={setSelectedFdi} />
          <ToothRow codes={LOWER_ROW} byFdi={byFdi} selectedFdi={selectedFdi} onSelect={setSelectedFdi} />
        </div>

        <Legend />

        {selectedFdi && (
          <ToothEditor
            key={selectedFdi}
            patientId={patientId}
            chartId={chartId}
            fdi={selectedFdi}
            current={byFdi.get(selectedFdi) ?? null}
            onClose={() => setSelectedFdi(null)}
          />
        )}
      </CardContent>
    </Card>
  );
}

function ToothRow({
  codes,
  byFdi,
  selectedFdi,
  onSelect,
}: {
  codes: string[];
  byFdi: Map<string, DentalToothState>;
  selectedFdi: string | null;
  onSelect: (fdi: string) => void;
}) {
  return (
    <div className="flex gap-1">
      {codes.map((fdi) => {
        const state = byFdi.get(fdi);
        const style = state ? TOOTH_STATUS_STYLES[state.status] : TOOTH_STATUS_STYLES.saine;
        return (
          <button
            key={fdi}
            onClick={() => onSelect(fdi)}
            title={state ? `${fdi} — ${state.status}` : `${fdi} — saine`}
            className={cn(
              "flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-md border text-[11px] font-medium transition-colors",
              style,
              selectedFdi === fdi && "ring-2 ring-accent ring-offset-1 ring-offset-bg",
            )}
          >
            {fdi}
          </button>
        );
      })}
    </div>
  );
}

function Legend() {
  return (
    <div className="flex flex-wrap gap-x-4 gap-y-1.5 border-t border-border pt-3">
      {Object.entries({
        saine: "Saine",
        cariee: "Cariée",
        obturee: "Obturée",
        couronnee: "Couronnée",
        extraite: "Extraite",
        absente: "Absente",
        implant: "Implant",
        bridge: "Bridge",
      }).map(([status, label]) => (
        <div key={status} className="flex items-center gap-1.5">
          <span className={cn("h-3 w-3 rounded border", TOOTH_STATUS_STYLES[status as keyof typeof TOOTH_STATUS_STYLES])} />
          <span className="text-[11px] text-text-subtle">{label}</span>
        </div>
      ))}
    </div>
  );
}

function ToothEditor({
  patientId,
  chartId,
  fdi,
  current,
  onClose,
}: {
  patientId: number;
  chartId: number;
  fdi: string;
  current: DentalToothState | null;
  onClose: () => void;
}) {
  const [values, setValues] = useState<SpecialtyFieldValues>({
    status: current?.status ?? "saine",
    notes: current?.notes ?? "",
  });
  const [errors, setErrors] = useState<SpecialtyFieldErrors>({});
  const [globalError, setGlobalError] = useState<string | null>(null);
  const mutation = useUpdateToothState(patientId, chartId);

  function handleSave() {
    setGlobalError(null);
    const nextErrors = validateSpecialtyFields(TOOTH_STATE_FIELDS, values);
    setErrors(nextErrors);
    if (Object.keys(nextErrors).length > 0) return;
    mutation.mutate(
      { fdi, payload: buildSpecialtyPayload(TOOTH_STATE_FIELDS, values) },
      {
        onSuccess: onClose,
        onError: (error) => setGlobalError(apiErrorMessage(error)),
      },
    );
  }

  return (
    <div className="space-y-3 rounded-md border border-border bg-bg p-4">
      <div className="flex items-center justify-between">
        <p className="text-sm font-medium text-text">Dent {fdi}</p>
        <Button variant="ghost" size="sm" onClick={onClose}>
          <X size={14} />
        </Button>
      </div>
      <SpecialtyForm
        columns={2}
        fields={TOOTH_STATE_FIELDS}
        values={values}
        errors={errors}
        disabled={mutation.isPending}
        onChange={(key, value) => setValues((v) => ({ ...v, [key]: value }))}
      />
      {globalError && (
        <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{globalError}</p>
      )}
      <Button size="sm" onClick={handleSave} disabled={mutation.isPending}>
        {mutation.isPending && <LoaderCircle size={14} className="animate-spin" />}
        Enregistrer
      </Button>
    </div>
  );
}
