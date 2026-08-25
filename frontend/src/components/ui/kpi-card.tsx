import { ArrowDownRight, ArrowUpRight, type LucideIcon } from "lucide-react";
import { cn } from "@/lib/utils";
import { Card } from "@/components/ui/card";

export interface KpiCardProps {
  label: string;
  value: string | number;
  unit?: string;
  variation?: number;
  variationLabel?: string;
  icon?: LucideIcon;
  className?: string;
}

export function KpiCard({
  label,
  value,
  unit,
  variation,
  variationLabel = "vs période précédente",
  icon: Icon,
  className,
}: KpiCardProps) {
  const isPositive = typeof variation === "number" && variation >= 0;

  return (
    <Card className={cn("p-5", className)}>
      <div className="flex items-start justify-between">
        <span className="text-xs font-medium uppercase tracking-wide text-text-subtle">
          {label}
        </span>
        {Icon && (
          <div className="rounded-md bg-surface-hover p-1.5 text-text-muted">
            <Icon size={16} strokeWidth={2} />
          </div>
        )}
      </div>

      <div className="mt-3 flex items-baseline gap-1.5">
        <span className="font-heading font-tabular text-2xl font-semibold text-text">
          {value}
        </span>
        {unit && <span className="text-sm text-text-muted">{unit}</span>}
      </div>

      {typeof variation === "number" && (
        <div className="mt-2 flex items-center gap-1 text-xs">
          <span
            className={cn(
              "flex items-center gap-0.5 font-medium",
              isPositive ? "text-success" : "text-danger",
            )}
          >
            {isPositive ? <ArrowUpRight size={14} /> : <ArrowDownRight size={14} />}
            {Math.abs(variation)}%
          </span>
          <span className="text-text-subtle">{variationLabel}</span>
        </div>
      )}
    </Card>
  );
}
