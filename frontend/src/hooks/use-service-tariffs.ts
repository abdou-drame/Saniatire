import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated, ServiceTariff } from "@/types/api";

function useInvalidateServiceTariffs() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: ["service-tariffs"] });
  };
}

export function useServiceTariffs() {
  return useQuery({
    queryKey: ["service-tariffs"],
    queryFn: async () => {
      const { data } = await api.get<Paginated<ServiceTariff>>("/service-tariffs");
      return data;
    },
  });
}

export interface CreateServiceTariffInput {
  code: string;
  libelle: string;
  categorie: string;
  prix_unitaire: number;
  actif?: boolean;
}

/**
 * Le code saisi ici doit correspondre exactement au billingTariffCode()
 * retourné côté backend par le modèle clinique concerné (Consultation,
 * LabOrder, ImagingOrder...) — BillingService::recordService() ne trouve
 * un tarif que par égalité stricte sur ce code. Un code approximatif ne
 * sera simplement jamais utilisé lors de la facturation, sans erreur.
 */
export function useCreateServiceTariff() {
  const invalidate = useInvalidateServiceTariffs();
  return useMutation({
    mutationFn: async (input: CreateServiceTariffInput) => {
      const { data } = await api.post<{ data: ServiceTariff }>("/service-tariffs", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export interface UpdateServiceTariffInput {
  id: number;
  libelle?: string;
  prix_unitaire?: number;
  actif?: boolean;
}

export function useUpdateServiceTariff() {
  const invalidate = useInvalidateServiceTariffs();
  return useMutation({
    mutationFn: async ({ id, ...input }: UpdateServiceTariffInput) => {
      const { data } = await api.patch<{ data: ServiceTariff }>(`/service-tariffs/${id}`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useDeleteServiceTariff() {
  const invalidate = useInvalidateServiceTariffs();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/service-tariffs/${id}`);
    },
    onSuccess: invalidate,
  });
}
