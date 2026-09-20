import { useMutation, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Patient } from "@/types/api";

export interface UpdatePatientInput {
  first_name: string;
  last_name: string;
  sex: "M" | "F";
  birth_date: string;
  phone?: string | null;
  email?: string | null;
  address?: string | null;
  profession?: string | null;
  nationality?: string | null;
  emergency_contact_name?: string | null;
  emergency_contact_phone?: string | null;
  emergency_contact_relationship?: string | null;
}

export function useUpdatePatient(patientId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: UpdatePatientInput) => {
      const { data } = await api.patch<{ data: Patient }>(`/patients/${patientId}`, input);
      return data.data;
    },
    onSuccess: () => {
      // Broad prefix: covers ["patients", patientId] (detail), ["patients",
      // "list"] and ["patients", "directory"] in one invalidation, same
      // pattern as the consultations diagnosis fix.
      queryClient.invalidateQueries({ queryKey: ["patients"] });
    },
  });
}
