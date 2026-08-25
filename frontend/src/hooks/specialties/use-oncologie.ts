import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated } from "@/types/api";
import type { OncoChemoCycle, OncoRecord, OncoResponseEvaluation } from "@/types/specialty";

async function fetchOncoRecord(patientId: number): Promise<OncoRecord | null> {
  const { data: list } = await api.get<Paginated<OncoRecord>>("/onco-records", {
    params: { patient_id: patientId },
  });
  const first = list.data[0];
  if (!first) return null;
  const { data: full } = await api.get<{ data: OncoRecord }>(`/onco-records/${first.id}`);
  return full.data;
}

export function oncoRecordQueryKey(patientId: number) {
  return ["specialty", "oncologie", patientId] as const;
}

export function useOncoRecord(patientId: number) {
  return useQuery({
    queryKey: oncoRecordQueryKey(patientId),
    queryFn: () => fetchOncoRecord(patientId),
    enabled: Number.isFinite(patientId),
  });
}

function useInvalidateOnco(patientId: number) {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: oncoRecordQueryKey(patientId) });
}

export function useCreateOncoRecord(patientId: number) {
  const invalidate = useInvalidateOnco(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: OncoRecord }>("/onco-records", {
        ...payload,
        patient_id: patientId,
      });
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddOncoChemoCycle(patientId: number, recordId: number) {
  const invalidate = useInvalidateOnco(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: OncoChemoCycle }>(`/onco-records/${recordId}/chemo-cycles`, payload);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddOncoResponseEvaluation(patientId: number, recordId: number) {
  const invalidate = useInvalidateOnco(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: OncoResponseEvaluation }>(
        `/onco-records/${recordId}/response-evaluations`,
        payload,
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}
