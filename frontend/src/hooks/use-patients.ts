import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated, Patient } from "@/types/api";

/**
 * Répertoire patients complet — même patron que fetchAllUserAccounts
 * (use-user-accounts.ts) : PatientController::index() pagine sans exposer
 * de contrôle de pagination propre à cet écran, donc on parcourt toutes
 * les pages pour que la recherche/le filtrage côté client (DataTable)
 * porte sur l'effectif entier de la structure, pas seulement la 1ère page.
 */
async function fetchAllPatients(): Promise<Patient[]> {
  const results: Patient[] = [];
  let page = 1;
  for (;;) {
    const { data } = await api.get<Paginated<Patient>>("/patients", { params: { page } });
    results.push(...data.data);
    if (!data.links.next) break;
    page++;
  }
  return results;
}

export function usePatients() {
  return useQuery({
    queryKey: ["patients", "list"],
    queryFn: fetchAllPatients,
  });
}
