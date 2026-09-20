import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { StructureDirectoryEntry } from "@/types/api";

/**
 * GET /structures/directory est gardé par la permission `referrals.create`
 * (seul consommateur prévu : le picker de destination d'un référencement).
 * Les appelants doivent passer `enabled: hasPermission("referrals.create")`
 * pour ne pas déclencher un 403 inutile — ce hook ne connaît pas
 * useAuth() lui-même, patron `useSites` dans use-sites.ts. La réponse est
 * déjà filtrée côté serveur (actives seulement, hors structure de
 * l'appelant) — ce n'est jamais une liste paginée, contrairement à
 * `/sites` ou `/users`.
 */
export function useStructuresDirectory(enabled = true) {
  return useQuery({
    queryKey: ["structures", "directory"],
    queryFn: async () => {
      const { data } = await api.get<{ data: StructureDirectoryEntry[] }>("/structures/directory");
      return data.data;
    },
    staleTime: 5 * 60_000,
    enabled,
  });
}
