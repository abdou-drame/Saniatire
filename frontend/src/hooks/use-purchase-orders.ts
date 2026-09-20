import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type {
  ApprovalRule,
  Paginated,
  PurchaseOrder,
  PurchaseOrderReception,
  PurchaseOrderStatut,
  PurchaseRequest,
  PurchaseRequestStatut,
  ReceptionControleQualite,
} from "@/types/api";

function useInvalidateAchats() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: ["purchase-requests"] });
    queryClient.invalidateQueries({ queryKey: ["purchase-orders"] });
    queryClient.invalidateQueries({ queryKey: ["products"] });
    queryClient.invalidateQueries({ queryKey: ["product-batches"] });
    queryClient.invalidateQueries({ queryKey: ["stock-alerts"] });
  };
}

export function usePurchaseRequests(filters: { statut?: PurchaseRequestStatut } = {}) {
  return useQuery({
    queryKey: ["purchase-requests", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<PurchaseRequest>>("/purchase-requests", {
        params: { statut: filters.statut },
      });
      return data;
    },
  });
}

export interface CreatePurchaseRequestInput {
  site_id: number;
  product_id: number;
  quantite: number;
}

export function useCreatePurchaseRequest() {
  const invalidate = useInvalidateAchats();
  return useMutation({
    mutationFn: async (input: CreatePurchaseRequestInput) => {
      const { data } = await api.post<{ data: PurchaseRequest }>("/purchase-requests", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useApprovePurchaseRequest() {
  const invalidate = useInvalidateAchats();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: PurchaseRequest }>(`/purchase-requests/${id}/approve`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useRejectPurchaseRequest() {
  const invalidate = useInvalidateAchats();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: PurchaseRequest }>(`/purchase-requests/${id}/reject`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useApprovalRules() {
  return useQuery({
    queryKey: ["approval-rules"],
    queryFn: async () => {
      const { data } = await api.get<Paginated<ApprovalRule>>("/approval-rules");
      return data;
    },
  });
}

export interface CreateApprovalRuleInput {
  level: number;
  min_amount: number;
  role_name: string;
}

export function useCreateApprovalRule() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateApprovalRuleInput) => {
      const { data } = await api.post<{ data: ApprovalRule }>("/approval-rules", input);
      return data.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["approval-rules"] }),
  });
}

export interface UpdateApprovalRuleInput {
  id: number;
  level?: number;
  min_amount?: number;
  role_name?: string;
}

export function useUpdateApprovalRule() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, ...input }: UpdateApprovalRuleInput) => {
      const { data } = await api.patch<{ data: ApprovalRule }>(`/approval-rules/${id}`, input);
      return data.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["approval-rules"] }),
  });
}

export function useDeleteApprovalRule() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/approval-rules/${id}`);
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["approval-rules"] }),
  });
}

export function usePurchaseOrders(filters: { statut?: PurchaseOrderStatut } = {}) {
  return useQuery({
    queryKey: ["purchase-orders", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<PurchaseOrder>>("/purchase-orders", {
        params: { statut: filters.statut },
      });
      return data;
    },
  });
}

export function usePurchaseOrder(id: number | undefined) {
  return useQuery({
    queryKey: ["purchase-orders", id],
    queryFn: async () => {
      const { data } = await api.get<{ data: PurchaseOrder }>(`/purchase-orders/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export interface CreatePurchaseOrderInput {
  site_id: number;
  supplier_id: number;
  items: {
    product_id: number;
    purchase_request_id?: number | null;
    quantite_commandee: number;
    prix_unitaire: number;
  }[];
}

export function useCreatePurchaseOrder() {
  const invalidate = useInvalidateAchats();
  return useMutation({
    mutationFn: async (input: CreatePurchaseOrderInput) => {
      const { data } = await api.post<{ data: PurchaseOrder }>("/purchase-orders", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useSubmitPurchaseOrder() {
  const invalidate = useInvalidateAchats();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: PurchaseOrder }>(`/purchase-orders/${id}/submit`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

// Le backend est seul décideur : il renvoie 403 avec le message exact
// "Le rôle « {role_name} » est requis pour valider ce niveau." si
// l'utilisateur n'a pas le rôle attendu pour le niveau en attente. Le
// frontend ne doit jamais tenter de deviner si l'utilisateur a le niveau
// suffisant avant d'appeler cette mutation.
export function useApprovePurchaseOrder() {
  const invalidate = useInvalidateAchats();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: PurchaseOrder }>(`/purchase-orders/${id}/approve`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useCancelPurchaseOrder() {
  const invalidate = useInvalidateAchats();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: PurchaseOrder }>(`/purchase-orders/${id}/cancel`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export interface CreateReceptionInput {
  purchaseOrderItemId: number;
  quantite_recue: number;
  date_reception: string;
  controle_qualite: ReceptionControleQualite;
  numero_lot: string;
  date_peremption: string;
}

export function useCreateReception() {
  const invalidate = useInvalidateAchats();
  return useMutation({
    mutationFn: async ({ purchaseOrderItemId, ...input }: CreateReceptionInput) => {
      const { data } = await api.post<{ data: PurchaseOrderReception }>(
        `/purchase-order-items/${purchaseOrderItemId}/receptions`,
        input,
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}
