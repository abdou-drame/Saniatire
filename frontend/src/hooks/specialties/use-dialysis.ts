import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated } from "@/types/api";
import type { DialysisProgram, DialysisSession, DialysisSessionVital } from "@/types/specialty";

async function fetchDialysisProgram(patientId: number): Promise<DialysisProgram | null> {
  const { data: list } = await api.get<Paginated<DialysisProgram>>("/dialysis-programs", {
    params: { patient_id: patientId },
  });
  const first = list.data[0];
  if (!first) return null;
  const { data: full } = await api.get<{ data: DialysisProgram }>(`/dialysis-programs/${first.id}`);
  return full.data;
}

export function dialysisProgramQueryKey(patientId: number) {
  return ["specialty", "dialyse", patientId] as const;
}

export function useDialysisProgram(patientId: number) {
  return useQuery({
    queryKey: dialysisProgramQueryKey(patientId),
    queryFn: () => fetchDialysisProgram(patientId),
    enabled: Number.isFinite(patientId),
  });
}

function useInvalidateDialysis(patientId: number) {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: dialysisProgramQueryKey(patientId) });
}

export function useCreateDialysisProgram(patientId: number) {
  const invalidate = useInvalidateDialysis(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: DialysisProgram }>("/dialysis-programs", {
        ...payload,
        patient_id: patientId,
      });
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddDialysisSession(patientId: number, programId: number) {
  const invalidate = useInvalidateDialysis(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: DialysisSession }>(`/dialysis-programs/${programId}/sessions`, payload);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddSessionVital(patientId: number, sessionId: number) {
  const invalidate = useInvalidateDialysis(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: DialysisSessionVital }>(`/dialysis-sessions/${sessionId}/vitals`, payload);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
