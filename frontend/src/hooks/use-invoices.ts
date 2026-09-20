import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { BillableItem, BillableItemStatut, Invoice, InvoiceStatus, Paginated } from "@/types/api";

function useInvalidateFacturation() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: ["invoices"] });
    queryClient.invalidateQueries({ queryKey: ["billable-items"] });
    queryClient.invalidateQueries({ queryKey: ["quotes"] });
  };
}

export function useInvoices(filters: { patient_id?: number; statut?: InvoiceStatus } = {}) {
  return useQuery({
    queryKey: ["invoices", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Invoice>>("/invoices", {
        params: { patient_id: filters.patient_id, statut: filters.statut },
      });
      return data;
    },
  });
}

export function useInvoice(id: number | undefined) {
  return useQuery({
    queryKey: ["invoices", id],
    queryFn: async () => {
      const { data } = await api.get<{ data: Invoice }>(`/invoices/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export interface CreateInvoiceInput {
  patient_id: number;
  site_id: number;
  billable_item_ids: number[];
}

/**
 * Le backend est seul décideur du montant total et de la répartition
 * assurance/patient (via InsuranceCoverageService) — cette mutation se
 * contente d'envoyer les billable_item_ids choisis et renvoie la Invoice
 * complète telle que calculée côté serveur.
 */
export function useCreateInvoice() {
  const invalidate = useInvalidateFacturation();
  return useMutation({
    mutationFn: async (input: CreateInvoiceInput) => {
      const { data } = await api.post<{ data: Invoice }>("/invoices", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useEmitInvoice() {
  const invalidate = useInvalidateFacturation();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: Invoice }>(`/invoices/${id}/emit`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useCancelInvoice() {
  const invalidate = useInvalidateFacturation();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: Invoice }>(`/invoices/${id}/cancel`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

/**
 * Liste des BillableItems (prestations facturables) d'un patient — aucune
 * création manuelle n'est jamais exposée ici : ces lignes apparaissent
 * automatiquement côté serveur dès qu'un acte clinique est facturé.
 */
export function useBillableItems(filters: { patient_id?: number; statut?: BillableItemStatut } = {}) {
  return useQuery({
    queryKey: ["billable-items", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<BillableItem>>("/billable-items", {
        params: { patient_id: filters.patient_id, statut: filters.statut },
      });
      return data;
    },
    enabled: Boolean(filters.patient_id),
  });
}
