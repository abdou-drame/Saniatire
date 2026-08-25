import { useQuery } from "@tanstack/react-query";
import { patientApi } from "@/lib/patient-api";
import type { PatientDocuments } from "@/types/api";

export function usePortalDocuments() {
  return useQuery({
    queryKey: ["portal-documents"],
    queryFn: async () => {
      const { data } = await patientApi.get<PatientDocuments>("/portail-patient/documents");
      return data;
    },
  });
}
