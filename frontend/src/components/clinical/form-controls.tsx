import type { ReactNode } from "react";
import { cn } from "@/lib/utils";

/**
 * Styling shared by every clinical form in the app (general consultation
 * form, the three specialty screens, and whichever specialty configs are
 * added later) — extracted here so a new specialty never needs to
 * reinvent input styling, only declare which fields it has.
 */
export function inputClass(hasError: boolean) {
  return cn(
    "h-9 w-full rounded-md border bg-bg px-3 text-sm text-text placeholder:text-text-subtle focus:outline-none focus:ring-1 disabled:opacity-60",
    hasError
      ? "border-danger focus:border-danger focus:ring-danger"
      : "border-border focus:border-border-strong focus:ring-accent",
  );
}

export function textareaClass() {
  return cn(
    "w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text placeholder:text-text-subtle",
    "focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent disabled:opacity-60",
  );
}

export function selectClass(hasError: boolean) {
  return cn(
    "h-9 w-full rounded-md border bg-bg px-3 text-sm text-text focus:outline-none focus:ring-1 disabled:opacity-60",
    hasError
      ? "border-danger focus:border-danger focus:ring-danger"
      : "border-border focus:border-border-strong focus:ring-accent",
  );
}

export function Field({
  label,
  error,
  required,
  children,
}: {
  label: string;
  error?: string;
  required?: boolean;
  children: ReactNode;
}) {
  return (
    <label className="block space-y-1.5">
      <span className="text-xs font-medium text-text-muted">
        {label}
        {required && <span className="text-danger"> *</span>}
      </span>
      {children}
      {error && <span className="block text-[11px] text-danger">{error}</span>}
    </label>
  );
}
