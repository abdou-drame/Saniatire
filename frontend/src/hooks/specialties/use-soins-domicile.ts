import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated } from "@/types/api";
import type { HomeCareRecord, HomeCareVisit } from "@/types/specialty";

async function fetchHomeCareRecord(patientId: number): Promise<HomeCareRecord | null> {
  const { data: list } = await api.get<Paginated<HomeCareRecord>>("/home-care-records", {
    params: { patient_id: patientId },
  });
  const first = list.data[0];
  if (!first) return null;
  const { data: full } = await api.get<{ data: HomeCareRecord }>(`/home-care-records/${first.id}`);
  return full.data;
}

export function homeCareRecordQueryKey(patientId: number) {
  return ["specialty", "soins_domicile", patientId] as const;
}

export function useHomeCareRecord(patientId: number) {
  return useQuery({
    queryKey: homeCareRecordQueryKey(patientId),
    queryFn: () => fetchHomeCareRecord(patientId),
    enabled: Number.isFinite(patientId),
  });
}

function useInvalidateHomeCare(patientId: number) {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: homeCareRecordQueryKey(patientId) });
}

export function useCreateHomeCareRecord(patientId: number) {
  const invalidate = useInvalidateHomeCare(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: HomeCareRecord }>("/home-care-records", {
        ...payload,
        patient_id: patientId,
      });
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddHomeCareVisit(patientId: number, recordId: number) {
  const invalidate = useInvalidateHomeCare(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: HomeCareVisit }>(`/home-care-records/${recordId}/visits`, payload);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
