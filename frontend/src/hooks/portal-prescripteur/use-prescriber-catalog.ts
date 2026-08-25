import { useQuery } from "@tanstack/react-query";
import { prescriberApi } from "@/lib/prescriber-api";
import type { PrescriberLoincCode, PrescriberPatientSearchResult, Site } from "@/types/api";

export function usePrescriberSites() {
  return useQuery({
    queryKey: ["prescriber-sites"],
    queryFn: async () => {
      const { data } = await prescriberApi.get<{ data: Site[] }>("/portail-prescripteur/sites");
      return data.data;
    },
  });
}

export function usePrescriberPatientSearch(search: string) {
  const term = search.trim();
  return useQuery({
    queryKey: ["prescriber-patients", term],
    queryFn: async () => {
      const { data } = await prescriberApi.get<{ data: PrescriberPatientSearchResult[] }>(
        "/portail-prescripteur/patients",
        { params: { search: term } },
      );
      return data.data;
    },
    enabled: term.length >= 2,
  });
}

export function usePrescriberLoincSearch(search: string) {
  const term = search.trim();
  return useQuery({
    queryKey: ["prescriber-loinc-codes", term],
    queryFn: async () => {
      const { data } = await prescriberApi.get<{ data: PrescriberLoincCode[] }>("/portail-prescripteur/loinc-codes", {
        params: term ? { search: term } : undefined,
      });
      return data.data;
    },
  });
}
