import { forwardRef, type HTMLAttributes } from "react";
import { cn } from "@/lib/utils";

export const Card = forwardRef<HTMLDivElement, HTMLAttributes<HTMLDivElement>>(
  ({ className, ...props }, ref) => (
    <div
      ref={ref}
      className={cn(
        "rounded-lg border border-border bg-surface shadow-[var(--shadow-card)]",
        className,
      )}
      {...props}
    />
  ),
);
Card.displayName = "Card";

export const CardHeader = forwardRef<HTMLDivElement, HTMLAttributes<HTMLDivElement>>(
  ({ className, ...props }, ref) => (
    <div
      ref={ref}
      className={cn(
        // Titre et actions passent à la ligne au lieu de pousser le bouton
        // hors de l'écran. Sous sm, une action placée directement dans
        // l'en-tête (bouton, lien ou groupe de boutons) prend toute la
        // largeur sous le titre.
        "flex flex-wrap items-center justify-between gap-x-4 gap-y-3 p-5 pb-3",
        "max-sm:[&>a]:w-full max-sm:[&>button]:w-full max-sm:[&>div:not(:first-child)]:w-full max-sm:[&>div:not(:first-child)]:flex-wrap",
        className,
      )}
      {...props}
    />
  ),
);
CardHeader.displayName = "CardHeader";

export const CardTitle = forwardRef<HTMLHeadingElement, HTMLAttributes<HTMLHeadingElement>>(
  ({ className, ...props }, ref) => (
    <h3 ref={ref} className={cn("font-heading text-sm font-semibold text-text", className)} {...props} />
  ),
);
CardTitle.displayName = "CardTitle";

export const CardContent = forwardRef<HTMLDivElement, HTMLAttributes<HTMLDivElement>>(
  ({ className, ...props }, ref) => (
    <div ref={ref} className={cn("p-5 pt-0", className)} {...props} />
  ),
);
CardContent.displayName = "CardContent";
