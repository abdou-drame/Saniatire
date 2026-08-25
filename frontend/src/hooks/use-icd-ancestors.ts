import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { IcdCode } from "@/types/api";

const MAX_DEPTH = 6;

/**
 * Walks parent_id up to the root to build the chapitre/groupe breadcrumb for
 * one already-selected code. Deliberately not used per search keystroke —
 * see CimCodeSearch — only for a code the user has committed to, so it's at
 * most a handful of sequential requests, cached by react-query afterwards.
 */
export function useIcdAncestors(code: IcdCode | null) {
  return useQuery({
    queryKey: ["icd-codes", "ancestors", code?.id],
    queryFn: async () => {
      const chain: IcdCode[] = [];
      let current = code!;
      let depth = 0;
      while (current.parent_id !== null && depth < MAX_DEPTH) {
        const { data } = await api.get<{ data: IcdCode }>(`/icd-codes/${current.parent_id}`);
        chain.unshift(data.data);
        current = data.data;
        depth++;
      }
      return chain;
    },
    enabled: code !== null,
    staleTime: Infinity,
  });
}
