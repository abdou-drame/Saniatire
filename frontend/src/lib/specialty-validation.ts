import type {
  SpecialtyFieldConfig,
  SpecialtyFieldErrors,
  SpecialtyFieldValues,
} from "@/types/specialty-config";

/**
 * Generic client-side mirror of the server's FormRequest rules for a set of
 * declaratively-configured fields — required/min/max only, exactly the
 * checks that give the user immediate feedback (e.g. Apgar out of 0-10).
 * The server remains the source of truth; this never replaces it.
 */
export function validateSpecialtyFields(
  fields: SpecialtyFieldConfig[],
  values: SpecialtyFieldValues,
): SpecialtyFieldErrors {
  const errors: SpecialtyFieldErrors = {};

  for (const field of fields) {
    if (field.type === "readonly") continue;
    const raw = (values[field.key] ?? "").trim();

    if (raw === "") {
      if (field.required) {
        errors[field.key] = "Ce champ est requis.";
      }
      continue;
    }

    if (field.type === "number") {
      const n = Number(raw);
      if (Number.isNaN(n)) {
        errors[field.key] = "Valeur numérique attendue.";
      } else if (field.min !== undefined && n < field.min) {
        errors[field.key] = `Doit être supérieur ou égal à ${field.min}.`;
      } else if (field.max !== undefined && n > field.max) {
        errors[field.key] = `Doit être inférieur ou égal à ${field.max}.`;
      }
    }
  }

  return errors;
}

/** Converts form-state strings (always strings, per the app's established input-state convention) into a JSON payload, dropping blanks to null and parsing numbers. */
export function buildSpecialtyPayload(
  fields: SpecialtyFieldConfig[],
  values: SpecialtyFieldValues,
): Record<string, string | number | string[] | null> {
  const payload: Record<string, string | number | string[] | null> = {};

  for (const field of fields) {
    if (field.type === "readonly") continue;
    const raw = (values[field.key] ?? "").trim();
    if (field.type === "checkboxes") {
      payload[field.key] = raw === "" ? null : raw.split(",");
      continue;
    }
    if (raw === "") {
      payload[field.key] = null;
      continue;
    }
    payload[field.key] = field.type === "number" ? Number(raw) : raw;
  }

  return payload;
}

export function emptySpecialtyValues(fields: SpecialtyFieldConfig[]): SpecialtyFieldValues {
  const values: SpecialtyFieldValues = {};
  for (const field of fields) {
    values[field.key] = "";
  }
  return values;
}
