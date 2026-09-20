import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Appointment, AppointmentStatus, Paginated } from "@/types/api";

export interface AppointmentFilters {
  from: string;
  to: string;
  practitionerId?: number;
  siteId?: number;
}

/**
 * The calendar (week/month views especially) needs the complete set of
 * appointments in range, not just the backend's default first page — so
 * this walks `links.next` rather than reading only page 1 (unlike
 * usePatientsDirectory, which accepts a first-page-only limitation because
 * it's a background lookup table, not the primary data being displayed).
 */
async function fetchAllAppointments(params: Record<string, string | number | undefined>): Promise<Appointment[]> {
  const results: Appointment[] = [];
  let page = 1;
  for (;;) {
    const { data } = await api.get<Paginated<Appointment>>("/appointments", { params: { ...params, page } });
    results.push(...data.data);
    if (!data.links.next) break;
    page++;
  }
  return results;
}

export function useAppointments(filters: AppointmentFilters) {
  return useQuery({
    queryKey: ["appointments", filters.from, filters.to, filters.practitionerId, filters.siteId],
    queryFn: () =>
      fetchAllAppointments({
        from: filters.from,
        to: filters.to,
        practitioner_id: filters.practitionerId,
        site_id: filters.siteId,
      }),
    refetchInterval: 60_000,
  });
}

export interface CreateAppointmentInput {
  site_id: number;
  patient_id: number;
  practitioner_id: number;
  starts_at: string;
  duration_minutes: number;
  reason?: string;
  force_override?: boolean;
}

export interface UpdateAppointmentInput {
  id: number;
  site_id?: number;
  practitioner_id?: number;
  starts_at?: string;
  duration_minutes?: number;
  reason?: string;
  status?: AppointmentStatus;
  force_override?: boolean;
}

function useInvalidateAppointments() {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: ["appointments"] });
}

export function useCreateAppointment() {
  const invalidate = useInvalidateAppointments();
  return useMutation({
    mutationFn: async (input: CreateAppointmentInput) => {
      const { data } = await api.post<{ data: Appointment }>("/appointments", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useUpdateAppointment() {
  const invalidate = useInvalidateAppointments();
  return useMutation({
    mutationFn: async ({ id, ...input }: UpdateAppointmentInput) => {
      const { data } = await api.patch<{ data: Appointment }>(`/appointments/${id}`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useCancelAppointment() {
  const invalidate = useInvalidateAppointments();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: Appointment }>(`/appointments/${id}/cancel`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
