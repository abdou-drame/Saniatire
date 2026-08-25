import { cva, type VariantProps } from "class-variance-authority";
import type { HTMLAttributes } from "react";
import { cn } from "@/lib/utils";

/**
 * Semantic status badge. The `status` variant maps 1:1 to the meaning of
 * the value being displayed (never picked per-screen) — reuse this mapping
 * everywhere a status is shown (tables, cards, detail pages).
 */
const badgeVariants = cva(
  "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium",
  {
    variants: {
      status: {
        success: "border-success/30 bg-success/10 text-success",
        warning: "border-warning/30 bg-warning/10 text-warning",
        danger: "border-danger/30 bg-danger/10 text-danger",
        accent: "border-accent/30 bg-accent/10 text-accent-light",
        accent2: "border-accent2/30 bg-accent2/10 text-accent2-light",
        neutral: "border-border-strong bg-surface-hover text-text-muted",
      },
    },
    defaultVariants: {
      status: "neutral",
    },
  },
);

export interface BadgeProps
  extends HTMLAttributes<HTMLSpanElement>,
    VariantProps<typeof badgeVariants> {
  dot?: boolean;
}

export function Badge({ className, status, dot = true, children, ...props }: BadgeProps) {
  return (
    <span className={cn(badgeVariants({ status, className }))} {...props}>
      {dot && (
        <span
          className={cn("h-1.5 w-1.5 rounded-full", {
            "bg-success": status === "success",
            "bg-warning": status === "warning",
            "bg-danger": status === "danger",
            "bg-accent": status === "accent",
            "bg-accent2": status === "accent2",
            "bg-text-subtle": !status || status === "neutral",
          })}
        />
      )}
      {children}
    </span>
  );
}
