import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { patientApi } from "@/lib/patient-api";
import type { Appointment, Paginated } from "@/types/api";

async function fetchAllAppointments(): Promise<Appointment[]> {
  const results: Appointment[] = [];
  let page = 1;
  for (;;) {
    const { data } = await patientApi.get<Paginated<Appointment>>("/portail-patient/rendez-vous", {
      params: { page },
    });
    results.push(...data.data);
    if (!data.links.next) break;
    page++;
  }
  return results;
}

export function usePortalAppointments() {
  return useQuery({
    queryKey: ["portal-appointments"],
    queryFn: fetchAllAppointments,
  });
}

export interface CreatePortalAppointmentInput {
  site_id: number;
  practitioner_id: number;
  starts_at: string;
  duration_minutes: number;
  reason?: string;
}

export function useCreatePortalAppointment() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreatePortalAppointmentInput) => {
      const { data } = await patientApi.post<{ data: Appointment }>("/portail-patient/rendez-vous", input);
      return data.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["portal-appointments"] }),
  });
}
