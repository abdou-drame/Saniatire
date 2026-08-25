import { useQuery } from "@tanstack/react-query";
import { LoaderCircle, Search } from "lucide-react";
import { useRef, useState, type KeyboardEvent } from "react";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { api } from "@/lib/api";
import { cn } from "@/lib/utils";
import type { IcdCode, Paginated } from "@/types/api";

const MIN_QUERY_LENGTH = 2;

export interface CimCodeSearchProps {
  onSelect: (code: IcdCode) => void;
  /** Codes to hide from results — e.g. already attached to the current consultation. */
  excludeIds?: number[];
  placeholder?: string;
  disabled?: boolean;
}

/**
 * Autonomous CIM/ICD code search — self-contained so it can be dropped into
 * any future specialty screen unchanged. Queries GET /icd-codes?search=...
 * live as the user types (debounced). The hierarchy (chapitre/groupe) isn't
 * denormalized on IcdCodeResource, so it isn't rendered per keystroke here
 * (that would mean N extra requests per result on every keystroke) — callers
 * render the hierarchy breadcrumb lazily once a code is actually selected,
 * via useIcdAncestors.
 */
export function CimCodeSearch({ onSelect, excludeIds = [], placeholder, disabled }: CimCodeSearchProps) {
  const [query, setQuery] = useState("");
  const [isFocused, setIsFocused] = useState(false);
  const [highlighted, setHighlighted] = useState(0);
  const debounced = useDebouncedValue(query.trim(), 300);
  const enabled = debounced.length >= MIN_QUERY_LENGTH;
  const inputRef = useRef<HTMLInputElement>(null);

  const { data, isFetching, isError } = useQuery({
    queryKey: ["icd-codes", "search", debounced],
    queryFn: async () => {
      const { data } = await api.get<Paginated<IcdCode>>("/icd-codes", {
        params: { search: debounced },
      });
      return data.data;
    },
    enabled,
    placeholderData: (previous) => previous,
  });

  const results = (data ?? []).filter((code) => !excludeIds.includes(code.id));
  const dropdownVisible = isFocused && query.trim().length > 0;

  function select(code: IcdCode) {
    onSelect(code);
    setQuery("");
    setHighlighted(0);
    inputRef.current?.blur();
  }

  function handleKeyDown(e: KeyboardEvent<HTMLInputElement>) {
    if (!dropdownVisible || results.length === 0) return;
    if (e.key === "ArrowDown") {
      e.preventDefault();
      setHighlighted((i) => Math.min(i + 1, results.length - 1));
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      setHighlighted((i) => Math.max(i - 1, 0));
    } else if (e.key === "Enter") {
      e.preventDefault();
      const code = results[highlighted];
      if (code) select(code);
    }
  }

  return (
    <div className="relative">
      <div className="relative">
        <Search
          size={15}
          strokeWidth={2}
          className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-text-subtle"
        />
        <input
          ref={inputRef}
          type="text"
          disabled={disabled}
          value={query}
          onChange={(e) => {
            setQuery(e.target.value);
            setHighlighted(0);
          }}
          onFocus={() => setIsFocused(true)}
          onBlur={() => setTimeout(() => setIsFocused(false), 150)}
          onKeyDown={handleKeyDown}
          placeholder={placeholder ?? "Rechercher un code CIM (code ou libellé)..."}
          className="h-9 w-full rounded-md border border-border bg-bg pl-9 pr-8 text-sm text-text placeholder:text-text-subtle focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent disabled:opacity-50"
        />
        {isFetching && enabled && (
          <LoaderCircle
            size={14}
            className="absolute right-3 top-1/2 -translate-y-1/2 animate-spin text-text-subtle"
          />
        )}
      </div>

      {dropdownVisible && (
        <div className="absolute left-0 top-11 z-50 w-full overflow-hidden rounded-md border border-border bg-surface shadow-lg">
          {!enabled && (
            <p className="px-3 py-2.5 text-xs text-text-subtle">Tapez au moins 2 caractères.</p>
          )}
          {enabled && isError && (
            <p className="px-3 py-2.5 text-xs text-danger">Recherche impossible pour le moment.</p>
          )}
          {enabled && !isError && !isFetching && results.length === 0 && (
            <p className="px-3 py-2.5 text-xs text-text-muted">Aucun code trouvé.</p>
          )}
          {enabled && results.length > 0 && (
            <ul className="max-h-72 overflow-y-auto py-1">
              {results.map((code, index) => (
                <li key={code.id}>
                  <button
                    type="button"
                    onMouseDown={(e) => e.preventDefault()}
                    onMouseEnter={() => setHighlighted(index)}
                    onClick={() => select(code)}
                    className={cn(
                      "flex w-full items-center justify-between gap-3 px-3 py-2 text-left",
                      index === highlighted ? "bg-surface-hover" : "hover:bg-surface-hover",
                    )}
                  >
                    <span className="min-w-0 truncate text-sm text-text">{code.label}</span>
                    <span className="flex shrink-0 items-center gap-2">
                      <span className="font-mono text-xs text-accent-light">{code.code}</span>
                      <span className="rounded border border-border px-1.5 py-0.5 text-[10px] text-text-subtle">
                        {code.version}
                      </span>
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}
