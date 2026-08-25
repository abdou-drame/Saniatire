/**
 * Declarative shape a specialty screen's root form (and any add-forms in
 * its history sections) is built from. Adding a field to an existing
 * specialty means adding one entry to its `SpecialtyFieldConfig[]` — the
 * generic renderer (`SpecialtyField`) and validator
 * (`validateSpecialtyFields`) never change.
 */
export type SpecialtyFieldType =
  | "text"
  | "textarea"
  | "number"
  | "select"
  | "checkboxes"
  | "date"
  | "datetime"
  | "readonly";

export interface SpecialtyFieldOption {
  value: string;
  label: string;
}

export interface SpecialtyFieldConfig {
  key: string;
  label: string;
  type: SpecialtyFieldType;
  required?: boolean;
  /** Numeric bounds, enforced client-side as an immediate-feedback mirror of the server's rules — never a replacement for them. */
  min?: number;
  max?: number;
  step?: string;
  unit?: string;
  /** For "select" (single value) and "checkboxes" (comma-joined values in the string form state). */
  options?: SpecialtyFieldOption[];
  placeholder?: string;
  /** Shown under the field, e.g. to explain why it's read-only. */
  help?: string;
}

export type SpecialtyFieldValues = Record<string, string>;
export type SpecialtyFieldErrors = Partial<Record<string, string>>;
