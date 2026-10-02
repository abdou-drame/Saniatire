import logoUrl from "@/assets/saliha-health-logo-cropped.png";
import markUrl from "@/assets/saliha-health-mark.png";
import { cn } from "@/lib/utils";

export const BRAND_NAME = "Saliha Health";

/*
  Le logo officiel est bleu marine (#003388) : posé directement sur le fond
  sombre de l'app, le contraste tombe à ~1,7:1. Il est donc toujours affiché
  sur une pastille claire, qui préserve les couleurs de la marque.
*/

/**
 * Logo complet (cœur + « SALIHA HEALTH »). Le texte fait partie de l'image :
 * ne pas répéter le nom à côté. `className` fixe la hauteur de la pastille
 * (ex. `h-[92px]`) ; la largeur suit le ratio de l'image.
 */
export function BrandLogo({ className }: { className?: string }) {
  return (
    <span className={cn("inline-flex shrink-0 rounded-xl bg-white p-1.5", className)}>
      <img src={logoUrl} alt={BRAND_NAME} className="h-full w-auto" />
    </span>
  );
}

/**
 * Symbole seul (cœur), pour les emplacements trop petits pour que le texte du
 * logo reste lisible (sidebar, en-têtes de portail). À accompagner du nom.
 */
export function BrandMark({ className }: { className?: string }) {
  return (
    <span className={cn("inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white p-1", className)}>
      <img src={markUrl} alt="" aria-hidden="true" className="h-full w-full" />
    </span>
  );
}
