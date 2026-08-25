import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated, Supplier } from "@/types/api";

export function useSuppliers() {
  return useQuery({
    queryKey: ["suppliers"],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Supplier>>("/suppliers");
      return data;
    },
  });
}

export interface CreateSupplierInput {
  nom: string;
  contact?: string;
  conditions_commerciales?: string;
}

export function useCreateSupplier() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateSupplierInput) => {
      const { data } = await api.post<{ data: Supplier }>("/suppliers", input);
      return data.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["suppliers"] }),
  });
}
