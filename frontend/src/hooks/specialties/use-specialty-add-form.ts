import { useState } from "react";
import { emptySpecialtyValues, validateSpecialtyFields } from "@/lib/specialty-validation";
import type { SpecialtyFieldConfig, SpecialtyFieldErrors, SpecialtyFieldValues } from "@/types/specialty-config";

/**
 * Local form state (values/errors/validate/reset) for one declaratively
 * configured add-form — shared by every "add an item to this history
 * section" flow across all three specialties (CPN visit, partogram
 * reading, newborn, postpartum visit, dental procedure, treatment plan
 * item, dialysis session, session vitals) instead of re-deriving the same
 * useState/validate boilerplate each time.
 */
export function useSpecialtyAddForm(fields: SpecialtyFieldConfig[]) {
  const [values, setValues] = useState<SpecialtyFieldValues>(() => emptySpecialtyValues(fields));
  const [errors, setErrors] = useState<SpecialtyFieldErrors>({});
  const [globalError, setGlobalError] = useState<string | null>(null);

  function onChange(key: string, value: string) {
    setValues((v) => ({ ...v, [key]: value }));
  }

  function validate(): boolean {
    const nextErrors = validateSpecialtyFields(fields, values);
    setErrors(nextErrors);
    return Object.keys(nextErrors).length === 0;
  }

  function reset() {
    setValues(emptySpecialtyValues(fields));
    setErrors({});
    setGlobalError(null);
  }

  return { values, errors, globalError, setGlobalError, onChange, validate, reset };
}
