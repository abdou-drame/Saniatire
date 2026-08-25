import { AlertTriangle } from "lucide-react";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export interface ErrorStateProps {
  message: string;
  onRetry?: () => void;
  className?: string;
}

/** Explicit error state for a failed query — never let a panel go blank on failure. */
export function ErrorState({ message, onRetry, className }: ErrorStateProps) {
  return (
    <div
      className={cn(
        "flex flex-col items-center justify-center gap-3 rounded-lg border border-dashed border-danger/30 bg-danger/5 px-6 py-14 text-center",
        className,
      )}
    >
      <div className="rounded-full bg-danger/10 p-3 text-danger">
        <AlertTriangle size={22} strokeWidth={1.5} />
      </div>
      <p className="max-w-sm text-sm text-text-muted">{message}</p>
      {onRetry && (
        <Button variant="secondary" size="sm" onClick={onRetry} className="mt-1">
          Réessayer
        </Button>
      )}
    </div>
  );
}
