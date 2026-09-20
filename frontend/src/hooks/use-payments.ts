import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { ModePaiement, Paginated, Payment, StatutMobileMoney } from "@/types/api";

function useInvalidatePayments() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: ["payments"] });
    // Un encaissement espèces peut modifier le total implicite de la session
    // de caisse ouverte (montant_ouverture + Σ espèces) affiché ailleurs.
    queryClient.invalidateQueries({ queryKey: ["cash-sessions"] });
  };
}

export function usePayments(filters: { invoice_id?: number; cash_session_id?: number } = {}) {
  return useQuery({
    queryKey: ["payments", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Payment>>("/payments", {
        params: {
          invoice_id: filters.invoice_id,
          cash_session_id: filters.cash_session_id,
        },
      });
      return data;
    },
  });
}

export interface CreatePaymentInput {
  invoice_id: number;
  site_id: number;
  mode_paiement: ModePaiement;
  reference_transaction?: string | null;
  statut_mobile_money?: StatutMobileMoney | null;
  montant: number;
}

export function useCreatePayment() {
  const invalidate = useInvalidatePayments();
  return useMutation({
    mutationFn: async (input: CreatePaymentInput) => {
      const { data } = await api.post<{ data: Payment }>("/payments", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
