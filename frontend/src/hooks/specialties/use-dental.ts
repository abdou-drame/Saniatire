import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated } from "@/types/api";
import type {
  DentalChart,
  DentalProcedure,
  DentalToothState,
  DentalTreatmentPlan,
  DentalTreatmentPlanItem,
} from "@/types/specialty";

async function fetchDentalChart(patientId: number): Promise<DentalChart | null> {
  const { data: list } = await api.get<Paginated<DentalChart>>("/dental-charts", {
    params: { patient_id: patientId },
  });
  const first = list.data[0];
  if (!first) return null;
  const { data: full } = await api.get<{ data: DentalChart }>(`/dental-charts/${first.id}`);
  return full.data;
}

export function dentalChartQueryKey(patientId: number) {
  return ["specialty", "dentaire", patientId] as const;
}

export function useDentalChart(patientId: number) {
  return useQuery({
    queryKey: dentalChartQueryKey(patientId),
    queryFn: () => fetchDentalChart(patientId),
    enabled: Number.isFinite(patientId),
  });
}

function useInvalidateDental(patientId: number) {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: dentalChartQueryKey(patientId) });
}

export function useCreateDentalChart(patientId: number) {
  const invalidate = useInvalidateDental(patientId);
  return useMutation({
    mutationFn: async (payload: { site_id: number; consultation_id?: number | null }) => {
      const { data } = await api.post<{ data: DentalChart }>("/dental-charts", {
        ...payload,
        patient_id: patientId,
      });
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useUpdateToothState(patientId: number, chartId: number) {
  const invalidate = useInvalidateDental(patientId);
  return useMutation({
    mutationFn: async ({ fdi, payload }: { fdi: string; payload: Record<string, unknown> }) => {
      const { data } = await api.put<{ data: DentalToothState }>(`/dental-charts/${chartId}/teeth/${fdi}`, payload);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddProcedure(patientId: number, chartId: number) {
  const invalidate = useInvalidateDental(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: DentalProcedure }>(`/dental-charts/${chartId}/procedures`, payload);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useCreateTreatmentPlan(patientId: number, chartId: number) {
  const invalidate = useInvalidateDental(patientId);
  return useMutation({
    mutationFn: async () => {
      const { data } = await api.post<{ data: DentalTreatmentPlan }>(`/dental-charts/${chartId}/treatment-plans`, {});
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddTreatmentPlanItem(patientId: number, planId: number) {
  const invalidate = useInvalidateDental(patientId);
  return useMutation({
    mutationFn: async (payload: Record<string, unknown>) => {
      const { data } = await api.post<{ data: DentalTreatmentPlanItem }>(
        `/dental-treatment-plans/${planId}/items`,
        payload,
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useMarkTreatmentPlanItemDone(patientId: number) {
  const invalidate = useInvalidateDental(patientId);
  return useMutation({
    mutationFn: async (itemId: number) => {
      const { data } = await api.patch<{ data: DentalTreatmentPlanItem }>(
        `/dental-treatment-plan-items/${itemId}`,
        { status: "realise" },
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}
