import { useMutation, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Patient } from "@/types/api";

export interface CreatePatientInput {
  first_name: string;
  last_name: string;
  sex: "M" | "F";
  birth_date: string;
  phone?: string;
  emergency_contact_name?: string;
  emergency_contact_phone?: string;
}

export interface CreatePatientResult {
  data: Patient;
  possible_duplicates: Array<{ id: number; patient_number: string }>;
}

/**
 * Backend always creates the record — duplicate detection (same
 * first/last name + birth_date) never blocks creation, it just returns
 * `possible_duplicates` alongside the new patient for the UI to surface
 * as a review prompt (see PatientController::store).
 */
export function useCreatePatient() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreatePatientInput) => {
      const { data } = await api.post<CreatePatientResult>("/patients", input);
      return data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ["patients"] });
    },
  });
}
