import { SpecialtyField } from "@/components/clinical/specialty-field";
import type { SpecialtyFieldConfig, SpecialtyFieldErrors, SpecialtyFieldValues } from "@/types/specialty-config";

/**
 * Renders a flat grid of declaratively-configured fields — the root form
 * of a specialty record, or the add-form inside a SpecialtyHistorySection.
 * Layout only; validation/submission stay with the caller so each specialty
 * keeps control of its own mutation (endpoints differ too much to force
 * into one generic hook).
 */
export function SpecialtyForm({
  fields,
  values,
  errors,
  disabled,
  onChange,
  columns = 3,
}: {
  fields: SpecialtyFieldConfig[];
  values: SpecialtyFieldValues;
  errors?: SpecialtyFieldErrors;
  disabled?: boolean;
  onChange: (key: string, value: string) => void;
  columns?: 1 | 2 | 3 | 4;
}) {
  const gridCols =
    columns === 1
      ? "grid-cols-1"
      : columns === 2
        ? "grid-cols-1 sm:grid-cols-2"
        : columns === 3
          ? "grid-cols-1 sm:grid-cols-2 lg:grid-cols-3"
          : "grid-cols-2 sm:grid-cols-3 lg:grid-cols-4";

  return (
    <div className={`grid gap-4 ${gridCols}`}>
      {fields.map((field) => (
        <SpecialtyField
          key={field.key}
          config={field}
          value={values[field.key] ?? ""}
          error={errors?.[field.key]}
          disabled={disabled}
          onChange={(value) => onChange(field.key, value)}
        />
      ))}
    </div>
  );
}
