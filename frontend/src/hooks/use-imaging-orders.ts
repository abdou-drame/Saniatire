import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type {
  ImagingExamType,
  ImagingOrder,
  ImagingOrderStats,
  ImagingOrderStatus,
  ImagingReport,
  ImagingStudy,
  Paginated,
} from "@/types/api";

export interface ImagingOrderFilters {
  patientId?: number;
  requesterId?: number;
  status?: ImagingOrderStatus;
  examType?: ImagingExamType;
}

export function useImagingOrders(filters: ImagingOrderFilters) {
  return useQuery({
    queryKey: ["imaging-orders", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<ImagingOrder>>("/imaging-orders", {
        params: {
          patient_id: filters.patientId,
          requester_id: filters.requesterId,
          status: filters.status,
          exam_type: filters.examType,
        },
      });
      return data;
    },
  });
}

export function useImagingOrder(id: number | undefined) {
  return useQuery({
    queryKey: ["imaging-orders", id],
    queryFn: async () => {
      const { data } = await api.get<{ data: ImagingOrder }>(`/imaging-orders/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export function useImagingOrderStats() {
  return useQuery({
    queryKey: ["imaging-orders", "stats"],
    queryFn: async () => {
      const { data } = await api.get<{ data: ImagingOrderStats }>("/imaging-orders/stats");
      return data.data;
    },
    refetchInterval: 30_000,
  });
}

export interface CreateImagingOrderInput {
  site_id: number;
  patient_id: number;
  prescriber_id: number;
  consultation_id?: number | null;
  exam_type: ImagingExamType;
  notes?: string;
}

export interface CreateImagingStudyInput {
  imagingOrderId: number;
  study_instance_uid: string;
  accession_number: string;
  modality: string;
  performed_at?: string;
  external_reference_url?: string;
  storage_reference?: string;
}

export interface CreateImagingReportInput {
  imagingStudyId: number;
  content: string;
}

function useInvalidateImagingOrders() {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: ["imaging-orders"] });
}

export function useCreateImagingOrder() {
  const invalidate = useInvalidateImagingOrders();
  return useMutation({
    mutationFn: async (input: CreateImagingOrderInput) => {
      const { data } = await api.post<{ data: ImagingOrder }>("/imaging-orders", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useCancelImagingOrder() {
  const invalidate = useInvalidateImagingOrders();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: ImagingOrder }>(`/imaging-orders/${id}/cancel`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useCreateImagingStudy() {
  const invalidate = useInvalidateImagingOrders();
  return useMutation({
    mutationFn: async ({ imagingOrderId, ...input }: CreateImagingStudyInput) => {
      const { data } = await api.post<{ data: ImagingStudy }>(`/imaging-orders/${imagingOrderId}/studies`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useCreateImagingReport() {
  const invalidate = useInvalidateImagingOrders();
  return useMutation({
    mutationFn: async ({ imagingStudyId, ...input }: CreateImagingReportInput) => {
      const { data } = await api.post<{ data: ImagingReport }>(`/imaging-studies/${imagingStudyId}/report`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useValidateImagingReport() {
  const invalidate = useInvalidateImagingOrders();
  return useMutation({
    mutationFn: async ({ id }: { id: number }) => {
      const { data } = await api.patch<{ data: ImagingReport }>(`/imaging-reports/${id}/validate`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useTransmitImagingStudy() {
  const invalidate = useInvalidateImagingOrders();
  return useMutation({
    mutationFn: async ({ id }: { id: number }) => {
      const { data } = await api.patch<{ data: ImagingStudy }>(`/imaging-studies/${id}/transmit`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
