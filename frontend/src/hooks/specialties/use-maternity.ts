import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated } from "@/types/api";
import type {
  MaternityDelivery,
  MaternityNewborn,
  MaternityPartogram,
  MaternityPartogramReading,
  MaternityPostpartumVisit,
  MaternityPrenatalVisit,
  MaternityRecord,
} from "@/types/specialty";

async function fetchMaternityRecord(patientId: number): Promise<MaternityRecord | null> {
  const { data: list } = await api.get<Paginated<MaternityRecord>>("/maternity-records", {
    params: { patient_id: patientId },
  });
  const first = list.data[0];
  if (!first) return null;
  const { data: full } = await api.get<{ data: MaternityRecord }>(`/maternity-records/${first.id}`);
  return full.data;
}

export function maternityRecordQueryKey(patientId: number) {
  return ["specialty", "maternite", patientId] as const;
}

export function useMaternityRecord(patientId: number) {
  return useQuery({
    queryKey: maternityRecordQueryKey(patientId),
    queryFn: () => fetchMaternityRecord(patientId),
    enabled: Number.isFinite(patientId),
  });
}

function useInvalidateMaternity(patientId: number) {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: maternityRecordQueryKey(patientId) });
}

export function useCreateMaternityRecord(patientId: number) {
  const invalidate = useInvalidateMaternity(patientId);
  return useMutation({
    mutationFn: async (payload: { site_id: number; last_menstrual_period_date: string; consultation_id?: number | null }) => {
      const { data } = await api.post<{ data: MaternityRecord }>("/maternity-records", {
        ...payload,
        patient_id: patientId,
      });
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddPrenatalVisit(patientId: number, recordId: number) {
  const invalidate = useInvalidateMaternity(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: MaternityPrenatalVisit }>(
        `/maternity-records/${recordId}/prenatal-visits`,
        payload,
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useCreatePartogram(patientId: number, recordId: number) {
  const invalidate = useInvalidateMaternity(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: MaternityPartogram }>(`/maternity-records/${recordId}/partogram`, payload);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddPartogramReading(patientId: number, partogramId: number) {
  const invalidate = useInvalidateMaternity(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: MaternityPartogramReading }>(
        `/maternity-partograms/${partogramId}/readings`,
        payload,
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useCreateDelivery(patientId: number, recordId: number) {
  const invalidate = useInvalidateMaternity(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: MaternityDelivery }>(`/maternity-records/${recordId}/delivery`, payload);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddNewborn(patientId: number, deliveryId: number) {
  const invalidate = useInvalidateMaternity(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: MaternityNewborn }>(`/maternity-deliveries/${deliveryId}/newborns`, payload);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddPostpartumVisit(patientId: number, recordId: number) {
  const invalidate = useInvalidateMaternity(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: MaternityPostpartumVisit }>(
        `/maternity-records/${recordId}/postpartum-visits`,
        payload,
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}
