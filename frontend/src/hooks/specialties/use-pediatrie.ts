import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated } from "@/types/api";
import type {
  PediatricDevelopmentObservation,
  PediatricGrowthMeasurement,
  PediatricRecord,
  PediatricVaccination,
} from "@/types/specialty";

async function fetchPediatricRecord(patientId: number): Promise<PediatricRecord | null> {
  const { data: list } = await api.get<Paginated<PediatricRecord>>("/pediatric-records", {
    params: { patient_id: patientId },
  });
  const first = list.data[0];
  if (!first) return null;
  const { data: full } = await api.get<{ data: PediatricRecord }>(`/pediatric-records/${first.id}`);
  return full.data;
}

export function pediatricRecordQueryKey(patientId: number) {
  return ["specialty", "pediatrie", patientId] as const;
}

export function usePediatricRecord(patientId: number) {
  return useQuery({
    queryKey: pediatricRecordQueryKey(patientId),
    queryFn: () => fetchPediatricRecord(patientId),
    enabled: Number.isFinite(patientId),
  });
}

function useInvalidatePediatric(patientId: number) {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: pediatricRecordQueryKey(patientId) });
}

export function useCreatePediatricRecord(patientId: number) {
  const invalidate = useInvalidatePediatric(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: PediatricRecord }>("/pediatric-records", {
        ...payload,
        patient_id: patientId,
      });
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddPediatricGrowthMeasurement(patientId: number, recordId: number) {
  const invalidate = useInvalidatePediatric(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: PediatricGrowthMeasurement }>(
        `/pediatric-records/${recordId}/growth-measurements`,
        payload,
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddPediatricVaccination(patientId: number, recordId: number) {
  const invalidate = useInvalidatePediatric(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: PediatricVaccination }>(
        `/pediatric-records/${recordId}/vaccinations`,
        payload,
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddPediatricDevelopmentObservation(patientId: number, recordId: number) {
  const invalidate = useInvalidatePediatric(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: PediatricDevelopmentObservation }>(
        `/pediatric-records/${recordId}/development-observations`,
        payload,
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}
