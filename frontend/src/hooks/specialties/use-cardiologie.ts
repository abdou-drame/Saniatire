import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated } from "@/types/api";
import type { CardioEcgResult, CardioReading, CardioRecord } from "@/types/specialty";

async function fetchCardioRecord(patientId: number): Promise<CardioRecord | null> {
  const { data: list } = await api.get<Paginated<CardioRecord>>("/cardio-records", {
    params: { patient_id: patientId },
  });
  const first = list.data[0];
  if (!first) return null;
  const { data: full } = await api.get<{ data: CardioRecord }>(`/cardio-records/${first.id}`);
  return full.data;
}

export function cardioRecordQueryKey(patientId: number) {
  return ["specialty", "cardiologie", patientId] as const;
}

export function useCardioRecord(patientId: number) {
  return useQuery({
    queryKey: cardioRecordQueryKey(patientId),
    queryFn: () => fetchCardioRecord(patientId),
    enabled: Number.isFinite(patientId),
  });
}

function useInvalidateCardio(patientId: number) {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: cardioRecordQueryKey(patientId) });
}

export function useCreateCardioRecord(patientId: number) {
  const invalidate = useInvalidateCardio(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: CardioRecord }>("/cardio-records", {
        ...payload,
        patient_id: patientId,
      });
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddCardioReading(patientId: number, recordId: number) {
  const invalidate = useInvalidateCardio(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: CardioReading }>(`/cardio-records/${recordId}/readings`, payload);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddCardioEcgResult(patientId: number, recordId: number) {
  const invalidate = useInvalidateCardio(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: CardioEcgResult }>(`/cardio-records/${recordId}/ecg-results`, payload);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
