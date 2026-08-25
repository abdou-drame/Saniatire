import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type {
  LabOrder,
  LabOrderStats,
  LabOrderStatus,
  LabResult,
  LabSample,
  Paginated,
} from "@/types/api";

export interface LabOrderFilters {
  patientId?: number;
  requesterId?: number;
  status?: LabOrderStatus;
}

export function useLabOrders(filters: LabOrderFilters) {
  return useQuery({
    queryKey: ["lab-orders", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<LabOrder>>("/lab-orders", {
        params: {
          patient_id: filters.patientId,
          requester_id: filters.requesterId,
          status: filters.status,
        },
      });
      return data;
    },
  });
}

export function useLabOrder(id: number | undefined) {
  return useQuery({
    queryKey: ["lab-orders", id],
    queryFn: async () => {
      const { data } = await api.get<{ data: LabOrder }>(`/lab-orders/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export function useLabOrderStats() {
  return useQuery({
    queryKey: ["lab-orders", "stats"],
    queryFn: async () => {
      const { data } = await api.get<{ data: LabOrderStats }>("/lab-orders/stats");
      return data.data;
    },
    refetchInterval: 30_000,
  });
}

export interface CreateLabOrderInput {
  site_id: number;
  patient_id: number;
  prescriber_id: number;
  consultation_id?: number | null;
  notes?: string;
  items: { loinc_code_id: number }[];
}

export interface RegisterLabSampleInput {
  labOrderId: number;
  barcode: string;
  sample_type: string;
  collected_at?: string;
}

export interface RegisterLabResultInput {
  labSampleId: number;
  lab_order_item_id: number;
  value: string;
  unit?: string;
  reference_min?: number;
  reference_max?: number;
  interpretation?: "normal" | "anormal" | "critique";
}

function useInvalidateLabOrders() {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: ["lab-orders"] });
}

export function useCreateLabOrder() {
  const invalidate = useInvalidateLabOrders();
  return useMutation({
    mutationFn: async (input: CreateLabOrderInput) => {
      const { data } = await api.post<{ data: LabOrder }>("/lab-orders", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useCancelLabOrder() {
  const invalidate = useInvalidateLabOrders();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: LabOrder }>(`/lab-orders/${id}/cancel`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useRegisterLabSample() {
  const invalidate = useInvalidateLabOrders();
  return useMutation({
    mutationFn: async ({ labOrderId, ...input }: RegisterLabSampleInput) => {
      const { data } = await api.post<{ data: LabSample }>(`/lab-orders/${labOrderId}/samples`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useRegisterLabResult() {
  const invalidate = useInvalidateLabOrders();
  return useMutation({
    mutationFn: async ({ labSampleId, ...input }: RegisterLabResultInput) => {
      const { data } = await api.post<{ data: LabResult }>(`/lab-samples/${labSampleId}/results`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useValidateLabResultTechnique() {
  const invalidate = useInvalidateLabOrders();
  return useMutation({
    mutationFn: async ({ id }: { id: number }) => {
      const { data } = await api.patch<{ data: LabResult }>(`/lab-results/${id}/validate-technique`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useValidateLabResultBiologique() {
  const invalidate = useInvalidateLabOrders();
  return useMutation({
    mutationFn: async ({ id }: { id: number }) => {
      const { data } = await api.patch<{ data: LabResult }>(`/lab-results/${id}/validate-biologique`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useTransmitLabResult() {
  const invalidate = useInvalidateLabOrders();
  return useMutation({
    mutationFn: async ({ id }: { id: number }) => {
      const { data } = await api.patch<{ data: LabResult }>(`/lab-results/${id}/transmit`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
