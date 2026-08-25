import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated, QueueEntry, QueueStatus } from "@/types/api";

export function useQueueEntries(siteId: number | undefined) {
  return useQuery({
    queryKey: ["queue-entries", "reception", siteId],
    queryFn: async () => {
      const { data } = await api.get<Paginated<QueueEntry>>("/queue-entries", {
        params: { site_id: siteId, include_exited: 1 },
      });
      return data.data;
    },
    enabled: Boolean(siteId),
    refetchInterval: 20_000,
  });
}

export interface RegisterArrivalInput {
  site_id: number;
  patient_id: number;
  appointment_id?: number | null;
  practitioner_id?: number | null;
  service: string;
  priority?: "normale" | "urgente" | "tres_urgente";
}

function useInvalidateQueue() {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: ["queue-entries"] });
}

export function useRegisterArrival() {
  const invalidate = useInvalidateQueue();
  return useMutation({
    mutationFn: async (input: RegisterArrivalInput) => {
      const { data } = await api.post<{ data: QueueEntry }>("/queue-entries", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useUpdateQueueStatus() {
  const invalidate = useInvalidateQueue();
  return useMutation({
    mutationFn: async ({ id, status }: { id: number; status: QueueStatus }) => {
      const { data } = await api.patch<{ data: QueueEntry }>(`/queue-entries/${id}/status`, { status });
      return data.data;
    },
    onSuccess: invalidate,
  });
}
