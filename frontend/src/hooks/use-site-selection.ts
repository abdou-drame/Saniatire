import { useEffect, useState } from "react";
import { useAuth } from "@/hooks/use-auth";
import { useSites } from "@/hooks/use-sites";
import type { Site } from "@/types/api";

/**
 * Résout le site à associer à une action (admission, prescription, création
 * d'un dossier de spécialité...). Un utilisateur rattaché à exactement un
 * site (médecin, infirmier — rattachement métier réel via `user_site`) le
 * voit préselectionné silencieusement, comme avant. Un utilisateur non
 * rattaché à un site personnel — administrateur, direction : supervision
 * transverse par conception (aucun site n'existe même encore quand son
 * compte est créé, cf. PlatformStructureController::store) — ou rattaché à
 * plusieurs sites doit choisir explicitement parmi les sites de sa
 * structure : l'action n'est plus jamais bloquée faute de site "par défaut"
 * inexistant.
 */
export function useSiteSelection() {
  const { user } = useAuth();
  const personalSites = user?.sites ?? [];
  const autoSiteId = personalSites.length === 1 ? personalSites[0].id : null;
  const needsManualSelection = autoSiteId === null;

  const structureSitesQuery = useSites(needsManualSelection);
  const [manualSiteId, setManualSiteId] = useState<number | null>(null);

  useEffect(() => {
    if (!needsManualSelection) setManualSiteId(null);
  }, [needsManualSelection]);

  const options: Site[] = personalSites.length > 0 ? personalSites : (structureSitesQuery.data ?? []);
  const siteId = autoSiteId ?? manualSiteId;

  return {
    siteId,
    setSiteId: setManualSiteId,
    needsManualSelection,
    options,
    isLoading: needsManualSelection && structureSitesQuery.isLoading,
  };
}
