import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import type { Site } from "@/types/api";

export interface SiteSelectFieldProps {
  siteId: number | null;
  onChange: (siteId: number | null) => void;
  options: Site[];
  isLoading?: boolean;
}

/**
 * Sélecteur explicite de site, affiché uniquement quand `useSiteSelection`
 * ne peut pas résoudre un site sans ambiguïté (utilisateur non rattaché à un
 * site unique — ex. administrateur). Si la structure n'a elle-même aucun
 * site, le message renvoie vers Structures & sites plutôt que de laisser un
 * sélecteur vide sans explication.
 */
export function SiteSelectField({ siteId, onChange, options, isLoading }: SiteSelectFieldProps) {
  return (
    <div>
      <Label>Site</Label>
      <Select
        value={siteId ?? ""}
        onChange={(e) => onChange(e.target.value ? Number(e.target.value) : null)}
        disabled={isLoading || options.length === 0}
      >
        <option value="">Sélectionner...</option>
        {options.map((site) => (
          <option key={site.id} value={site.id}>
            {site.name}
          </option>
        ))}
      </Select>
      {!isLoading && options.length === 0 && (
        <p className="mt-1 text-xs text-danger">
          Aucun site n'existe encore pour votre structure — créez-en un dans Structures &amp; sites.
        </p>
      )}
    </div>
  );
}
