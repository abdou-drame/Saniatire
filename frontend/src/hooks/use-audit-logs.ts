import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { AuditLogPage } from "@/types/api";

/**
 * `GET /audit-logs` — gardé côté serveur par la permission `audit.view`
 * (rôles `conformite` et `administrateur` uniquement, voir
 * app/Domain/Audit/README.md). Ce hook ne vérifie rien lui-même : c'est à
 * l'appelant (AuditRoute) de garder l'écran sur `hasPermission("audit.view")`
 * avant même de monter ce hook.
 *
 * Réponse brute d'un paginator Laravel (`->paginate()` sans Resource), donc
 * `AuditLogPage` — pas `Paginated<T>`. `page` est lu nativement par
 * `paginate()` côté Laravel via `?page=`.
 */
export interface AuditLogFilters {
  user_id?: number;
  action?: string;
  table?: string;
  from?: string; // yyyy-mm-dd
  to?: string;
  per_page?: number;
  page?: number;
}

export function useAuditLogs(filters: AuditLogFilters = {}) {
  return useQuery({
    queryKey: ["audit-logs", filters],
    queryFn: async () => {
      const { data } = await api.get<AuditLogPage>("/audit-logs", { params: filters });
      return data;
    },
  });
}
