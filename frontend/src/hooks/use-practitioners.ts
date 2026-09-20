import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Practitioner } from "@/types/api";

export type PractitionerRole = "medecin" | "chirurgien" | "anesthesiste";

export function usePractitioners(role?: PractitionerRole) {
  return useQuery({
    queryKey: ["practitioners", role ?? "medecin"],
    queryFn: async () => {
      const { data } = await api.get<{ data: Practitioner[] }>("/practitioners", {
        params: role ? { role } : undefined,
      });
      return data.data;
    },
    staleTime: 5 * 60_000,
  });
}
