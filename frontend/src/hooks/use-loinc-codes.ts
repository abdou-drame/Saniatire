import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated, PrescriberLoincCode } from "@/types/api";

/** Staff-side LOINC catalog search (permission `laboratoire.view`) — distinct from the
 * external prescriber-portal version in `use-prescriber-catalog.ts`, which hits a
 * differently-typed, token-scoped endpoint. */
export function useLoincCodeSearch(search: string) {
  const term = search.trim();
  return useQuery({
    queryKey: ["loinc-codes", term],
    queryFn: async () => {
      const { data } = await api.get<Paginated<PrescriberLoincCode>>("/loinc-codes", {
        params: { search: term },
      });
      return data.data;
    },
    enabled: term.length >= 2,
  });
}
