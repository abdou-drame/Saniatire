import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated } from "@/types/api";
import type { KineProgram, KineSession } from "@/types/specialty";

async function fetchKineProgram(patientId: number): Promise<KineProgram | null> {
  const { data: list } = await api.get<Paginated<KineProgram>>("/kine-programs", {
    params: { patient_id: patientId },
  });
  const first = list.data[0];
  if (!first) return null;
  const { data: full } = await api.get<{ data: KineProgram }>(`/kine-programs/${first.id}`);
  return full.data;
}

export function kineProgramQueryKey(patientId: number) {
  return ["specialty", "kinesitherapie", patientId] as const;
}

export function useKineProgram(patientId: number) {
  return useQuery({
    queryKey: kineProgramQueryKey(patientId),
    queryFn: () => fetchKineProgram(patientId),
    enabled: Number.isFinite(patientId),
  });
}

function useInvalidateKine(patientId: number) {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: kineProgramQueryKey(patientId) });
}

export function useCreateKineProgram(patientId: number) {
  const invalidate = useInvalidateKine(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: KineProgram }>("/kine-programs", {
        ...payload,
        patient_id: patientId,
      });
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddKineSession(patientId: number, programId: number) {
  const invalidate = useInvalidateKine(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: KineSession }>(`/kine-programs/${programId}/sessions`, payload);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
