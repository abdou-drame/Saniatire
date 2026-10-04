import { ArrowUpDown, ChevronLeft, ChevronRight } from "lucide-react";
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from "react";
import { cn } from "@/lib/utils";
import { TableSkeleton } from "@/components/ui/loading-state";

export interface DataTableColumn<T> {
  key: string;
  header: string;
  sortable?: boolean;
  align?: "left" | "right" | "center";
  /** Custom cell renderer — use this to drop a <Badge> or any element in a cell. */
  render?: (row: T) => ReactNode;
  /** Value used for default rendering and for sorting. Required if `render` is omitted. */
  accessor?: (row: T) => string | number;
  /**
   * Masque la colonne en dessous de ce seuil (sm 640, md 768, lg 1024,
   * xl 1280). À réserver aux colonnes secondaires, pour que les colonnes
   * critiques (statut, actions) restent visibles sans défilement.
   */
  hideBelow?: "sm" | "md" | "lg" | "xl";
}

// Classes écrites en entier pour que Tailwind les détecte.
const HIDE_BELOW_CLASS = {
  sm: "hidden sm:table-cell",
  md: "hidden md:table-cell",
  lg: "hidden lg:table-cell",
  xl: "hidden xl:table-cell",
} as const;

/**
 * Suit la position de défilement horizontal du tableau pour signaler les
 * colonnes masquées à gauche ou à droite (ombre de bord + flèche).
 */
function useHorizontalOverflow() {
  const ref = useRef<HTMLDivElement>(null);
  const [overflow, setOverflow] = useState({ left: false, right: false });

  const update = useCallback(() => {
    const el = ref.current;
    if (!el) return;
    const left = el.scrollLeft > 1;
    const right = el.scrollLeft + el.clientWidth < el.scrollWidth - 1;
    setOverflow((prev) => (prev.left === left && prev.right === right ? prev : { left, right }));
  }, []);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    update();
    el.addEventListener("scroll", update, { passive: true });
    window.addEventListener("resize", update);
    const observer = typeof ResizeObserver !== "undefined" ? new ResizeObserver(update) : null;
    observer?.observe(el);
    if (el.firstElementChild) observer?.observe(el.firstElementChild);
    return () => {
      el.removeEventListener("scroll", update);
      window.removeEventListener("resize", update);
      observer?.disconnect();
    };
  });

  function scrollBy(direction: -1 | 1) {
    const el = ref.current;
    if (!el) return;
    el.scrollBy({ left: direction * Math.max(120, el.clientWidth * 0.7), behavior: "smooth" });
  }

  return { ref, overflow, scrollBy };
}

export interface DataTableProps<T> {
  columns: DataTableColumn<T>[];
  data: T[];
  rowKey: (row: T) => string | number;
  pageSize?: number;
  isLoading?: boolean;
  emptyState?: ReactNode;
  onRowClick?: (row: T) => void;
}

export function DataTable<T>({
  columns,
  data,
  rowKey,
  pageSize = 10,
  isLoading = false,
  emptyState,
  onRowClick,
}: DataTableProps<T>) {
  const [sort, setSort] = useState<{ key: string; direction: "asc" | "desc" } | null>(null);
  const [page, setPage] = useState(0);
  const scroller = useHorizontalOverflow();

  const sorted = useMemo(() => {
    if (!sort) return data;
    const column = columns.find((c) => c.key === sort.key);
    if (!column?.accessor) return data;

    return [...data].sort((a, b) => {
      const va = column.accessor!(a);
      const vb = column.accessor!(b);
      const cmp = typeof va === "number" && typeof vb === "number" ? va - vb : String(va).localeCompare(String(vb));
      return sort.direction === "asc" ? cmp : -cmp;
    });
  }, [data, sort, columns]);

  const pageCount = Math.max(1, Math.ceil(sorted.length / pageSize));
  const currentPage = Math.min(page, pageCount - 1);
  const pageRows = sorted.slice(currentPage * pageSize, currentPage * pageSize + pageSize);

  function toggleSort(column: DataTableColumn<T>) {
    if (!column.sortable) return;
    setSort((prev) => {
      if (prev?.key !== column.key) return { key: column.key, direction: "asc" };
      if (prev.direction === "asc") return { key: column.key, direction: "desc" };
      return null;
    });
    setPage(0);
  }

  if (isLoading) {
    return <TableSkeleton columns={columns.length} />;
  }

  if (data.length === 0 && emptyState) {
    return <>{emptyState}</>;
  }

  return (
    <div className="overflow-hidden rounded-lg border border-border">
      <div className="relative">
        <div ref={scroller.ref} className="overflow-x-auto [&::-webkit-scrollbar-track]:bg-surface-hover/60">
          <table className="w-full border-collapse text-sm">
            <thead>
              <tr className="border-b border-border bg-surface-hover/50">
                {columns.map((column) => (
                  <th
                    key={column.key}
                    className={cn(
                      "px-4 py-2.5 text-xs font-medium uppercase tracking-wide text-text-subtle",
                      column.hideBelow && HIDE_BELOW_CLASS[column.hideBelow],
                      column.align === "right" && "text-right",
                      column.align === "center" && "text-center",
                      column.align !== "right" && column.align !== "center" && "text-left",
                      column.sortable && "cursor-pointer select-none hover:text-text-muted",
                    )}
                    onClick={() => toggleSort(column)}
                  >
                    <span className="inline-flex items-center gap-1">
                      {column.header}
                      {column.sortable && <ArrowUpDown size={12} />}
                    </span>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {pageRows.map((row) => (
                <tr
                  key={rowKey(row)}
                  onClick={onRowClick ? () => onRowClick(row) : undefined}
                  className={cn(
                    "border-b border-border bg-surface last:border-b-0 hover:bg-surface-hover/60",
                    onRowClick && "cursor-pointer",
                  )}
                >
                  {columns.map((column) => (
                    <td
                      key={column.key}
                      className={cn(
                        "px-4 py-3 text-text",
                        column.hideBelow && HIDE_BELOW_CLASS[column.hideBelow],
                        column.align === "right" && "text-right",
                        column.align === "center" && "text-center",
                      )}
                    >
                      {column.render ? column.render(row) : (column.accessor?.(row) ?? "—")}
                    </td>
                  ))}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        {/* Indice de défilement : ombre + flèche sur le bord qui cache des colonnes. */}
        {scroller.overflow.left && (
          <button
            type="button"
            onClick={() => scroller.scrollBy(-1)}
            aria-label="Faire défiler le tableau vers la gauche"
            className="absolute inset-y-0 left-0 flex w-9 items-center justify-start bg-gradient-to-r from-bg via-bg/70 to-transparent pl-1 text-text-muted hover:text-text"
          >
            <ChevronLeft size={18} />
          </button>
        )}
        {scroller.overflow.right && (
          <button
            type="button"
            onClick={() => scroller.scrollBy(1)}
            aria-label="Faire défiler le tableau vers la droite"
            className="absolute inset-y-0 right-0 flex w-9 items-center justify-end bg-gradient-to-l from-bg via-bg/70 to-transparent pr-1 text-text-muted hover:text-text"
          >
            <ChevronRight size={18} />
          </button>
        )}
      </div>
      {(scroller.overflow.left || scroller.overflow.right) && (
        <p className="border-t border-border px-4 py-1.5 text-[11px] text-text-subtle">
          Faites défiler le tableau horizontalement pour voir toutes les colonnes.
        </p>
      )}

      {pageCount > 1 && (
        <div className="flex items-center justify-between border-t border-border px-4 py-2.5 text-xs text-text-muted">
          <span>
            Page {currentPage + 1} / {pageCount} — {sorted.length} résultats
          </span>
          <div className="flex items-center gap-1">
            <button
              className="rounded p-1 hover:bg-surface-hover disabled:pointer-events-none disabled:opacity-40"
              disabled={currentPage === 0}
              onClick={() => setPage((p) => Math.max(0, p - 1))}
              aria-label="Page précédente"
            >
              <ChevronLeft size={16} />
            </button>
            <button
              className="rounded p-1 hover:bg-surface-hover disabled:pointer-events-none disabled:opacity-40"
              disabled={currentPage >= pageCount - 1}
              onClick={() => setPage((p) => Math.min(pageCount - 1, p + 1))}
              aria-label="Page suivante"
            >
              <ChevronRight size={16} />
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
