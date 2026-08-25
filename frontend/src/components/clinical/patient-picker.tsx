import { Plus, Search, UserRound, X } from "lucide-react";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { usePatientSearch } from "@/hooks/use-patient-search";
import type { Patient } from "@/types/api";

export interface PatientPickerProps {
  value: Patient | null;
  onChange: (patient: Patient | null) => void;
  onRequestCreate?: () => void;
  placeholder?: string;
  disabled?: boolean;
}

/**
 * Generic patient search-and-select — same isolation contract as
 * CimCodeSearch: only `onChange`/`onRequestCreate` props, no dependency on
 * whatever screen renders it (used here for both appointment creation and
 * front-desk check-in). Backed by usePatientSearch, so it hits the real
 * `GET /patients?search=` endpoint exactly like the topbar search.
 */
export function PatientPicker({ value, onChange, onRequestCreate, placeholder, disabled }: PatientPickerProps) {
  const [isFocused, setIsFocused] = useState(false);
  const search = usePatientSearch();
  const dropdownVisible = isFocused && search.isOpen;

  if (value) {
    return (
      <div className="flex items-center justify-between gap-3 rounded-md border border-border bg-surface px-3 py-2">
        <div className="flex items-center gap-2 text-sm text-text">
          <UserRound size={15} className="text-text-subtle" />
          <span className="font-medium">
            {value.first_name} {value.last_name}
          </span>
          <span className="font-tabular text-xs text-text-subtle">{value.patient_number}</span>
        </div>
        {!disabled && (
          <button
            type="button"
            onClick={() => onChange(null)}
            className="rounded p-1 text-text-muted hover:bg-surface-hover hover:text-text"
            aria-label="Changer de patient"
          >
            <X size={14} />
          </button>
        )}
      </div>
    );
  }

  return (
    <div className="relative">
      <div className="relative">
        <Search size={14} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-text-subtle" />
        <input
          type="search"
          disabled={disabled}
          placeholder={placeholder ?? "Rechercher un patient (nom, n° dossier)..."}
          value={search.query}
          onChange={(e) => search.setQuery(e.target.value)}
          onFocus={() => setIsFocused(true)}
          onBlur={() => setTimeout(() => setIsFocused(false), 150)}
          className="h-9 w-full rounded-md border border-border bg-surface pl-9 pr-3 text-sm text-text placeholder:text-text-subtle focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent disabled:opacity-50"
        />
      </div>

      {dropdownVisible && (
        <div className="absolute left-0 top-11 z-50 w-full overflow-hidden rounded-md border border-border bg-surface shadow-lg">
          {search.tooShort && <p className="px-3 py-2.5 text-xs text-text-subtle">Tapez au moins 2 caractères.</p>}
          {!search.tooShort && search.isSearching && <p className="px-3 py-2.5 text-xs text-text-muted">Recherche…</p>}
          {!search.tooShort && !search.isSearching && (
            <>
              {search.results.length === 0 ? (
                <p className="px-3 py-2.5 text-xs text-text-muted">Aucun patient trouvé.</p>
              ) : (
                <ul className="max-h-56 overflow-y-auto py-1">
                  {search.results.map((patient) => (
                    <li key={patient.id}>
                      <button
                        type="button"
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={() => {
                          onChange(patient);
                          search.setQuery("");
                        }}
                        className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left hover:bg-surface-hover"
                      >
                        <span className="text-sm text-text">
                          {patient.first_name} {patient.last_name}
                        </span>
                        <span className="font-tabular text-xs text-text-subtle">{patient.patient_number}</span>
                      </button>
                    </li>
                  ))}
                </ul>
              )}
              {onRequestCreate && (
                <button
                  type="button"
                  onMouseDown={(e) => e.preventDefault()}
                  onClick={onRequestCreate}
                  className="flex w-full items-center gap-2 border-t border-border px-3 py-2.5 text-left text-sm text-accent-light hover:bg-surface-hover"
                >
                  <Plus size={14} />
                  Créer un nouveau patient
                </button>
              )}
            </>
          )}
        </div>
      )}

      {onRequestCreate && !search.isOpen && (
        <Button type="button" variant="ghost" size="sm" className="mt-1.5" onClick={onRequestCreate}>
          <Plus size={14} />
          Nouveau patient
        </Button>
      )}
    </div>
  );
}
