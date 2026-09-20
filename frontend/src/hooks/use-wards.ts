import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Bed, BedStatus, Paginated, Ward } from "@/types/api";

export function useWards(siteId?: number) {
  return useQuery({
    queryKey: ["wards", "list", siteId],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Ward>>("/wards", {
        params: { site_id: siteId },
      });
      return data.data;
    },
  });
}

export function useWard(id: number | undefined) {
  return useQuery({
    queryKey: ["wards", id],
    queryFn: async () => {
      const { data } = await api.get<{ data: Ward }>(`/wards/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export interface WardInput {
  site_id: number;
  name: string;
}

function useInvalidateWards() {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: ["wards"] });
}

export function useCreateWard() {
  const invalidate = useInvalidateWards();
  return useMutation({
    mutationFn: async (input: WardInput) => {
      const { data } = await api.post<{ data: Ward }>("/wards", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useUpdateWard() {
  const invalidate = useInvalidateWards();
  return useMutation({
    mutationFn: async ({ id, ...input }: Partial<WardInput> & { id: number }) => {
      const { data } = await api.patch<{ data: Ward }>(`/wards/${id}`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export interface BedInput {
  site_id: number;
  ward_id: number;
  room_number: string;
  bed_label: string;
  status?: BedStatus;
}

function useInvalidateBeds() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: ["beds"] });
    queryClient.invalidateQueries({ queryKey: ["wards"] });
  };
}

export function useCreateBed() {
  const invalidate = useInvalidateBeds();
  return useMutation({
    mutationFn: async (input: BedInput) => {
      const { data } = await api.post<{ data: Bed }>("/beds", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useUpdateBed() {
  const invalidate = useInvalidateBeds();
  return useMutation({
    mutationFn: async ({ id, ...input }: Partial<BedInput> & { id: number }) => {
      const { data } = await api.patch<{ data: Bed }>(`/beds/${id}`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
