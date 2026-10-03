import { useMutation } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { PaymentTransaction } from "@/types/api";

/**
 * Renouvellement en self-service : aucun paramètre, le backend reprend la
 * formule et la périodicité de la dernière période de la structure de
 * l'utilisateur connecté et renvoie le lien de paiement DexPay.
 */
export function useSubscriptionSelfCheckout() {
  return useMutation({
    mutationFn: async () => {
      const { data } = await api.post<{ data: PaymentTransaction }>("/subscription/dexpay-checkout");
      return data.data;
    },
  });
}
