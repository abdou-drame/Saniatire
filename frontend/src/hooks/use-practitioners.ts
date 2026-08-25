import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Practitioner } from "@/types/api";

export function usePractitioners() {
  return useQuery({
    queryKey: ["practitioners"],
    queryFn: async () => {
      const { data } = await api.get<{ data: Practitioner[] }>("/practitioners");
      return data.data;
    },
    staleTime: 5 * 60_000,
  });
}
