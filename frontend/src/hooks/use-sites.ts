import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated, Site } from "@/types/api";

export function useSites() {
  return useQuery({
    queryKey: ["sites"],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Site>>("/sites");
      return data.data;
    },
    staleTime: 5 * 60_000,
  });
}
