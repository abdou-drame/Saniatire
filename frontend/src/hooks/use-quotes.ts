import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Invoice, Paginated, Quote } from "@/types/api";

function useInvalidateQuotes() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: ["quotes"] });
  };
}

/**
 * useConvertQuote invalide aussi ["invoices"] en plus de ["quotes"], car la
 * conversion crée une nouvelle Invoice en brouillon côté serveur.
 */
function useInvalidateQuotesAndInvoices() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: ["quotes"] });
    queryClient.invalidateQueries({ queryKey: ["invoices"] });
  };
}

export function useQuotes(filters: { patient_id?: number } = {}) {
  return useQuery({
    queryKey: ["quotes", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Quote>>("/quotes", {
        params: { patient_id: filters.patient_id },
      });
      return data;
    },
  });
}

export function useQuote(id: number | undefined) {
  return useQuery({
    queryKey: ["quotes", id],
    queryFn: async () => {
      const { data } = await api.get<{ data: Quote }>(`/quotes/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export interface CreateQuoteInput {
  patient_id: number;
  site_id: number;
  items: {
    libelle: string;
    categorie: string;
    quantite: number;
    prix_unitaire: number;
  }[];
}

/**
 * Le backend calcule montant_total et crée directement le devis en statut
 * "emis" (pas d'étape brouillon->emis séparée côté devis, contrairement aux
 * factures). Le frontend n'envoie que les lignes saisies.
 */
export function useCreateQuote() {
  const invalidate = useInvalidateQuotes();
  return useMutation({
    mutationFn: async (input: CreateQuoteInput) => {
      const { data } = await api.post<{ data: Quote }>("/quotes", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useCancelQuote() {
  const invalidate = useInvalidateQuotes();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: Quote }>(`/quotes/${id}/cancel`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

/**
 * La conversion recalcule la répartition assurance côté serveur au moment de
 * la conversion (pas une copie des chiffres du devis, car la couverture du
 * patient peut avoir changé depuis) et crée une nouvelle Invoice en
 * brouillon (renvoyée directement en réponse) — d'où l'invalidation
 * conjointe de ["quotes"] et ["invoices"].
 */
export function useConvertQuote() {
  const invalidate = useInvalidateQuotesAndInvoices();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: Invoice }>(`/quotes/${id}/convert`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
