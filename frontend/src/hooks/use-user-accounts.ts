import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { Paginated, UserAccount } from "@/types/api";

/**
 * L'écran de gestion des comptes a besoin de l'effectif complet (recherche/
 * filtres côté client, comme PersonnelPage) — mêmes patron de parcours de
 * pages que fetchAllUsers dans use-users-directory.ts, mais sur la forme
 * complète UserAccount (email, is_active, last_login_at...) plutôt que la
 * forme minimale StaffUser utilisée ailleurs pour résoudre des noms.
 */
async function fetchAllUserAccounts(): Promise<UserAccount[]> {
  const results: UserAccount[] = [];
  let page = 1;
  for (;;) {
    const { data } = await api.get<Paginated<UserAccount>>("/users", { params: { page } });
    results.push(...data.data);
    if (!data.links.next) break;
    page++;
  }
  return results;
}

function useInvalidateUserAccounts() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: ["user-accounts"] });
    // Le répertoire minimal (use-users-directory.ts) lit le même endpoint —
    // le tenir à jour évite d'afficher un nom/rôle périmé ailleurs dans
    // l'app après une création/édition de compte.
    queryClient.invalidateQueries({ queryKey: ["users", "directory"] });
  };
}

export function useUserAccounts() {
  return useQuery({
    queryKey: ["user-accounts"],
    queryFn: fetchAllUserAccounts,
  });
}

/**
 * Noms de rôles Spatie disponibles (GET /users/roles, ajouté pour cet
 * écran). Aucun libellé français n'est fourni par le backend : le
 * sélecteur affiche roleLabel(role) avec repli sur le nom brut du rôle
 * pour les rôles non encore présents dans role-labels.ts — jamais une
 * traduction inventée.
 */
export function useUserRoles() {
  return useQuery({
    queryKey: ["user-roles"],
    queryFn: async () => {
      const { data } = await api.get<{ data: string[] }>("/users/roles");
      return data.data;
    },
    staleTime: 5 * 60_000,
  });
}

export interface UserAccountInput {
  first_name: string;
  last_name: string;
  email: string;
  phone?: string;
  password?: string;
  is_active?: boolean;
  role: string;
  site_ids?: number[];
}

export function useCreateUserAccount() {
  const invalidate = useInvalidateUserAccounts();
  return useMutation({
    mutationFn: async (input: UserAccountInput) => {
      const { data } = await api.post<{ data: UserAccount }>("/users", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useUpdateUserAccount() {
  const invalidate = useInvalidateUserAccounts();
  return useMutation({
    mutationFn: async ({ id, ...input }: { id: number } & Partial<UserAccountInput>) => {
      const { data } = await api.put<{ data: UserAccount }>(`/users/${id}`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
