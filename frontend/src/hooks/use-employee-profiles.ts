import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { EmployeeProfile, Paginated, StatutEmploi } from "@/types/api";

function useInvalidateEmployeeProfiles() {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: ["employee-profiles"] });
}

export function useEmployeeProfiles(filters: { statutEmploi?: StatutEmploi; siteId?: number } = {}) {
  return useQuery({
    queryKey: ["employee-profiles", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<EmployeeProfile>>("/employee-profiles", {
        params: { statut_emploi: filters.statutEmploi, site_id: filters.siteId },
      });
      return data;
    },
  });
}

export interface CreateEmployeeProfileInput {
  user_id: number;
  date_embauche?: string;
  type_contrat?: string;
  statut_emploi?: StatutEmploi;
  qualification?: string;
  numero_ordre?: string;
}

export interface UpdateEmployeeProfileInput extends Partial<CreateEmployeeProfileInput> {
  id: number;
}

export function useCreateEmployeeProfile() {
  const invalidate = useInvalidateEmployeeProfiles();
  return useMutation({
    mutationFn: async (input: CreateEmployeeProfileInput) => {
      const { data } = await api.post<{ data: EmployeeProfile }>("/employee-profiles", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useUpdateEmployeeProfile() {
  const invalidate = useInvalidateEmployeeProfiles();
  return useMutation({
    mutationFn: async ({ id, ...input }: UpdateEmployeeProfileInput) => {
      const { data } = await api.put<{ data: EmployeeProfile }>(`/employee-profiles/${id}`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
