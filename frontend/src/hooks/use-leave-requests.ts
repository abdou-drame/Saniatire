import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type {
  LeaveRequest,
  LeaveRequestOverlapWarning,
  LeaveRequestStatut,
  LeaveRequestType,
  Paginated,
} from "@/types/api";

function useInvalidateLeaveRequests() {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: ["leave-requests"] });
}

export function useLeaveRequests(filters: { userId?: number; statut?: LeaveRequestStatut } = {}) {
  return useQuery({
    queryKey: ["leave-requests", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<LeaveRequest>>("/leave-requests", {
        params: { user_id: filters.userId, statut: filters.statut },
      });
      return data;
    },
  });
}

export interface CreateLeaveRequestInput {
  type: LeaveRequestType;
  date_debut: string;
  date_fin: string;
  commentaire?: string;
}

export function useCreateLeaveRequest() {
  const invalidate = useInvalidateLeaveRequests();
  return useMutation({
    mutationFn: async (input: CreateLeaveRequestInput) => {
      const { data } = await api.post<{ data: LeaveRequest }>("/leave-requests", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

// La réponse de /validate est non-standard : elle inclut des `warnings`
// (chevauchements avec des gardes/astreintes planifiées) en plus de la
// ressource. Ces warnings doivent être remontés tels quels à l'appelant
// (la page RH les affiche), donc la mutation ne les extrait/jette pas.
export interface ValidateLeaveRequestResult {
  data: LeaveRequest;
  warnings: LeaveRequestOverlapWarning[];
}

export function useValidateLeaveRequest() {
  const invalidate = useInvalidateLeaveRequests();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.patch<ValidateLeaveRequestResult>(`/leave-requests/${id}/validate`);
      return data;
    },
    onSuccess: invalidate,
  });
}

export function useRefuseLeaveRequest() {
  const invalidate = useInvalidateLeaveRequests();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.patch<{ data: LeaveRequest }>(`/leave-requests/${id}/refuse`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
