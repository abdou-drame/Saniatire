import { Badge } from "@/components/ui/badge";
import type { Structure } from "@/types/api";

/** Statut d'une structure, même correspondance partout : archivée > active / suspendue. */
export function StructureStatusBadge({ structure }: { structure: Pick<Structure, "is_active" | "archived_at"> }) {
  if (structure.archived_at) return <Badge status="danger">Archivée</Badge>;
  return (
    <Badge status={structure.is_active ? "success" : "neutral"}>{structure.is_active ? "Active" : "Suspendue"}</Badge>
  );
}
