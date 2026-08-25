import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated } from "@/types/api";
import type { MentalHealthRecord, MentalHealthScaleScore } from "@/types/specialty";

async function fetchMentalHealthRecord(patientId: number): Promise<MentalHealthRecord | null> {
  const { data: list } = await api.get<Paginated<MentalHealthRecord>>("/mental-health-records", {
    params: { patient_id: patientId },
  });
  const first = list.data[0];
  if (!first) return null;
  const { data: full } = await api.get<{ data: MentalHealthRecord }>(`/mental-health-records/${first.id}`);
  return full.data;
}

export function mentalHealthRecordQueryKey(patientId: number) {
  return ["specialty", "sante_mentale", patientId] as const;
}

export function useMentalHealthRecord(patientId: number) {
  return useQuery({
    queryKey: mentalHealthRecordQueryKey(patientId),
    queryFn: () => fetchMentalHealthRecord(patientId),
    enabled: Number.isFinite(patientId),
  });
}

function useInvalidateMentalHealth(patientId: number) {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: mentalHealthRecordQueryKey(patientId) });
}

export function useCreateMentalHealthRecord(patientId: number) {
  const invalidate = useInvalidateMentalHealth(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: MentalHealthRecord }>("/mental-health-records", {
        ...payload,
        patient_id: patientId,
      });
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddMentalHealthScaleScore(patientId: number, recordId: number) {
  const invalidate = useInvalidateMentalHealth(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: MentalHealthScaleScore }>(
        `/mental-health-records/${recordId}/scale-scores`,
        payload,
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}
