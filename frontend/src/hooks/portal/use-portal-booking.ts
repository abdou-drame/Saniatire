import { useQuery } from "@tanstack/react-query";
import { patientApi } from "@/lib/patient-api";
import type { Practitioner, Site } from "@/types/api";

export function usePortalSites() {
  return useQuery({
    queryKey: ["portal-sites"],
    queryFn: async () => {
      const { data } = await patientApi.get<{ data: Site[] }>("/portail-patient/sites");
      return data.data;
    },
  });
}

export function usePortalPractitioners() {
  return useQuery({
    queryKey: ["portal-practitioners"],
    queryFn: async () => {
      const { data } = await patientApi.get<{ data: Practitioner[] }>("/portail-patient/practitioners");
      return data.data;
    },
  });
}

export interface CreneauxParams {
  practitionerId: number;
  from: string;
  to: string;
  durationMinutes?: number;
}

export function usePortalCreneaux(params: CreneauxParams | null) {
  return useQuery({
    queryKey: ["portal-creneaux", params?.practitionerId, params?.from, params?.to, params?.durationMinutes],
    queryFn: async () => {
      const { data } = await patientApi.get<{ creneaux: string[] }>("/portail-patient/creneaux-disponibles", {
        params: {
          practitioner_id: params!.practitionerId,
          from: params!.from,
          to: params!.to,
          duration_minutes: params!.durationMinutes,
        },
      });
      return data.creneaux;
    },
    enabled: params !== null,
  });
}
