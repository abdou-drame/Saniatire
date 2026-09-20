import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated, PractitionerPlanning, WorkSchedule, WorkScheduleType } from "@/types/api";

function useInvalidateWorkSchedules() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: ["work-schedules"] });
    queryClient.invalidateQueries({ queryKey: ["work-schedules", "planning"] });
  };
}

export function useWorkSchedules(filters: { userId?: number; type?: WorkScheduleType; siteId?: number } = {}) {
  return useQuery({
    queryKey: ["work-schedules", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<WorkSchedule>>("/work-schedules", {
        params: { user_id: filters.userId, type: filters.type, site_id: filters.siteId },
      });
      return data;
    },
  });
}

export function usePractitionerPlanning(userId: number | undefined, from: string, to: string) {
  return useQuery({
    queryKey: ["work-schedules", "planning", userId, from, to],
    queryFn: async () => {
      const { data } = await api.get<{ data: PractitionerPlanning }>(`/employees/${userId}/planning`, {
        params: { from, to },
      });
      return data.data;
    },
    enabled: Boolean(userId),
  });
}

export interface CreateWorkScheduleInput {
  user_id: number;
  site_id: number;
  /** `null` est explicitement transmis (et non omis) pour effacer le champ non
   * retenu par le mode récurrent/ponctuel — WorkScheduleRequest les déclare
   * tous deux `nullable` avec un `required_without` croisé. */
  jour_semaine?: number | null;
  date?: string | null;
  heure_debut: string;
  heure_fin: string;
  type?: WorkScheduleType;
}

export interface UpdateWorkScheduleInput extends Partial<CreateWorkScheduleInput> {
  id: number;
}

export function useCreateWorkSchedule() {
  const invalidate = useInvalidateWorkSchedules();
  return useMutation({
    mutationFn: async (input: CreateWorkScheduleInput) => {
      const { data } = await api.post<{ data: WorkSchedule }>("/work-schedules", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useUpdateWorkSchedule() {
  const invalidate = useInvalidateWorkSchedules();
  return useMutation({
    mutationFn: async ({ id, ...input }: UpdateWorkScheduleInput) => {
      const { data } = await api.put<{ data: WorkSchedule }>(`/work-schedules/${id}`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useDeleteWorkSchedule() {
  const invalidate = useInvalidateWorkSchedules();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/work-schedules/${id}`);
    },
    onSuccess: invalidate,
  });
}
