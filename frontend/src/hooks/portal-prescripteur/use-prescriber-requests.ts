import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { prescriberApi } from "@/lib/prescriber-api";
import type { PrescriberImagingOrder, PrescriberLabOrder } from "@/types/api";

export function usePrescriberLabOrders() {
  return useQuery({
    queryKey: ["prescriber-demandes-labo"],
    queryFn: async () => {
      const { data } = await prescriberApi.get<{ data: PrescriberLabOrder[] }>("/portail-prescripteur/demandes-labo");
      return data.data;
    },
  });
}

export function usePrescriberImagingOrders() {
  return useQuery({
    queryKey: ["prescriber-demandes-imagerie"],
    queryFn: async () => {
      const { data } = await prescriberApi.get<{ data: PrescriberImagingOrder[] }>(
        "/portail-prescripteur/demandes-imagerie",
      );
      return data.data;
    },
  });
}

export interface CreateLabOrderInput {
  patient_id: number;
  site_id: number;
  notes?: string;
  items: { loinc_code_id: number }[];
}

export function useCreatePrescriberLabOrder() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateLabOrderInput) => {
      const { data } = await prescriberApi.post<{ data: { id: number } }>(
        "/portail-prescripteur/demandes-labo",
        input,
      );
      return data.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["prescriber-demandes-labo"] }),
  });
}

export interface CreateImagingOrderInput {
  patient_id: number;
  site_id: number;
  exam_type: "radio" | "echo" | "scanner" | "irm";
  notes?: string;
}

export function useCreatePrescriberImagingOrder() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateImagingOrderInput) => {
      const { data } = await prescriberApi.post<{ data: { id: number } }>(
        "/portail-prescripteur/demandes-imagerie",
        input,
      );
      return data.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["prescriber-demandes-imagerie"] }),
  });
}
