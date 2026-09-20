import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { BalanceAgee } from "@/types/api";

export interface BalanceAgeeFilters {
  patient_id?: number;
  insurance_convention_id?: number;
}

/**
 * `buckets` et `lignes` (dont chaque `solde`) sont calculés côté backend
 * dans CreancesController::balanceAgee — ce hook ne fait que les récupérer
 * et les exposer tels quels ; aucun total ni bucket n'est jamais recalculé
 * ou re-agrégé côté frontend.
 */
export function useBalanceAgee(filters: BalanceAgeeFilters = {}) {
  return useQuery({
    queryKey: ["creances", "balance-agee", filters],
    queryFn: async () => {
      const { data } = await api.get<BalanceAgee>("/creances/balance-agee", { params: filters });
      return data;
    },
  });
}
