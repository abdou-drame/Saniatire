import { Field, inputClass, selectClass, textareaClass } from "@/components/clinical/form-controls";
import { cn } from "@/lib/utils";
import type { SpecialtyFieldConfig } from "@/types/specialty-config";

/**
 * Renders exactly one field from a specialty's declarative config. This is
 * the single reuse point that lets a new specialty (or a new field on an
 * existing one) require zero changes here — only a config entry.
 */
export function SpecialtyField({
  config,
  value,
  error,
  disabled,
  onChange,
}: {
  config: SpecialtyFieldConfig;
  value: string;
  error?: string;
  disabled?: boolean;
  onChange: (value: string) => void;
}) {
  if (config.type === "readonly") {
    return (
      <Field label={config.label}>
        <div className="flex h-9 items-center rounded-md border border-dashed border-border bg-bg px-3 text-sm text-text-muted">
          {value || "—"}
        </div>
        {config.help && <p className="text-[11px] text-text-subtle">{config.help}</p>}
      </Field>
    );
  }

  if (config.type === "textarea") {
    return (
      <Field label={config.label} error={error} required={config.required}>
        <textarea
          disabled={disabled}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          rows={3}
          placeholder={config.placeholder}
          className={textareaClass()}
        />
      </Field>
    );
  }

  if (config.type === "checkboxes") {
    const selected = value ? value.split(",") : [];
    function toggle(optionValue: string) {
      const next = selected.includes(optionValue)
        ? selected.filter((v) => v !== optionValue)
        : [...selected, optionValue];
      onChange(next.join(","));
    }
    return (
      <Field label={config.label} error={error} required={config.required}>
        <div className="flex flex-wrap gap-x-4 gap-y-1.5 rounded-md border border-border bg-bg px-3 py-2">
          {(config.options ?? []).map((option) => (
            <label key={option.value} className="flex items-center gap-1.5 text-sm text-text">
              <input
                type="checkbox"
                disabled={disabled}
                checked={selected.includes(option.value)}
                onChange={() => toggle(option.value)}
                className="h-3.5 w-3.5 rounded border-border-strong accent-accent"
              />
              {option.label}
            </label>
          ))}
        </div>
      </Field>
    );
  }

  if (config.type === "select") {
    return (
      <Field label={config.label} error={error} required={config.required}>
        <select
          disabled={disabled}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className={selectClass(Boolean(error))}
        >
          <option value="">—</option>
          {(config.options ?? []).map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
      </Field>
    );
  }

  return (
    <Field label={config.label} error={error} required={config.required}>
      <div className="relative">
        <input
          type={
            config.type === "number"
              ? "number"
              : config.type === "date"
                ? "date"
                : config.type === "datetime"
                  ? "datetime-local"
                  : "text"
          }
          disabled={disabled}
          step={config.type === "number" ? (config.step ?? "1") : undefined}
          value={value}
          placeholder={config.placeholder}
          onChange={(e) => onChange(e.target.value)}
          className={cn(inputClass(Boolean(error)), config.unit && "pr-10")}
        />
        {config.unit && (
          <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-text-subtle">
            {config.unit}
          </span>
        )}
      </div>
    </Field>
  );
}
