import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated } from "@/types/api";
import type { PmaCycleMonitoring, PmaRecord, PmaStimulationProtocol } from "@/types/specialty";

async function fetchPmaRecord(patientId: number): Promise<PmaRecord | null> {
  const { data: list } = await api.get<Paginated<PmaRecord>>("/pma-records", {
    params: { patient_id: patientId },
  });
  const first = list.data[0];
  if (!first) return null;
  const { data: full } = await api.get<{ data: PmaRecord }>(`/pma-records/${first.id}`);
  return full.data;
}

export function pmaRecordQueryKey(patientId: number) {
  return ["specialty", "pma", patientId] as const;
}

export function usePmaRecord(patientId: number) {
  return useQuery({
    queryKey: pmaRecordQueryKey(patientId),
    queryFn: () => fetchPmaRecord(patientId),
    enabled: Number.isFinite(patientId),
  });
}

function useInvalidatePma(patientId: number) {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: pmaRecordQueryKey(patientId) });
}

export function useCreatePmaRecord(patientId: number) {
  const invalidate = useInvalidatePma(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: PmaRecord }>("/pma-records", {
        ...payload,
        patient_id: patientId,
      });
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddPmaStimulationProtocol(patientId: number, recordId: number) {
  const invalidate = useInvalidatePma(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: PmaStimulationProtocol }>(
        `/pma-records/${recordId}/stimulation-protocols`,
        payload,
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddPmaCycleMonitoring(patientId: number, recordId: number) {
  const invalidate = useInvalidatePma(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: PmaCycleMonitoring }>(
        `/pma-records/${recordId}/cycle-monitorings`,
        payload,
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}
