import type { LucideIcon } from "lucide-react";
import { LoaderCircle, Plus, X } from "lucide-react";
import { useState, type ReactNode } from "react";
import { SpecialtyForm } from "@/components/clinical/specialty-form";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import type { SpecialtyFieldConfig, SpecialtyFieldErrors, SpecialtyFieldValues } from "@/types/specialty-config";

export interface SpecialtyHistoryAddForm {
  fields: SpecialtyFieldConfig[];
  values: SpecialtyFieldValues;
  errors?: SpecialtyFieldErrors;
  onChange: (key: string, value: string) => void;
  onSubmit: () => void;
  isSubmitting?: boolean;
  submitLabel?: string;
  globalError?: string | null;
  columns?: 1 | 2 | 3 | 4;
}

/**
 * Generic "history" card: a chronological list of child records (CPN
 * visits, dialysis sessions, dental procedures...) plus an optional
 * collapsible declarative add-form. One reuse point that every specialty's
 * repeatable collections share — only `items`/`renderItem`/`addForm` change
 * per use.
 */
export function SpecialtyHistorySection<T extends { id: number }>({
  title,
  icon: Icon,
  items,
  renderItem,
  emptyLabel = "Aucun élément enregistré.",
  addForm,
  addButtonLabel = "Ajouter",
}: {
  title: string;
  icon?: LucideIcon;
  items: T[];
  renderItem: (item: T, index: number) => ReactNode;
  emptyLabel?: string;
  addForm?: SpecialtyHistoryAddForm;
  addButtonLabel?: string;
}) {
  const [adding, setAdding] = useState(false);

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          {Icon && <Icon size={15} className="text-text-subtle" />}
          {title}
        </CardTitle>
        {addForm && !adding && (
          <Button variant="secondary" size="sm" onClick={() => setAdding(true)}>
            <Plus size={14} />
            {addButtonLabel}
          </Button>
        )}
        {addForm && adding && (
          <Button variant="ghost" size="sm" onClick={() => setAdding(false)}>
            <X size={14} />
            Annuler
          </Button>
        )}
      </CardHeader>
      <CardContent className="space-y-4">
        {addForm && adding && (
          <div className="space-y-3 rounded-md border border-border bg-bg p-4">
            <SpecialtyForm
              fields={addForm.fields}
              values={addForm.values}
              errors={addForm.errors}
              disabled={addForm.isSubmitting}
              onChange={addForm.onChange}
              columns={addForm.columns ?? 3}
            />
            {addForm.globalError && (
              <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
                {addForm.globalError}
              </p>
            )}
            <Button
              size="sm"
              disabled={addForm.isSubmitting}
              onClick={() => {
                addForm.onSubmit();
              }}
            >
              {addForm.isSubmitting && <LoaderCircle size={14} className="animate-spin" />}
              {addForm.submitLabel ?? "Enregistrer"}
            </Button>
          </div>
        )}

        {items.length === 0 ? (
          <p className="text-xs text-text-subtle">{emptyLabel}</p>
        ) : (
          <ol className="space-y-2">
            {items.map((item, index) => (
              <li key={item.id}>{renderItem(item, index)}</li>
            ))}
          </ol>
        )}
      </CardContent>
    </Card>
  );
}
