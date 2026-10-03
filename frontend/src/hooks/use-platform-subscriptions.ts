import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { platformApi } from "@/lib/platform-api";
import type {
  BillingPeriod,
  PaymentTransaction,
  Plan,
  Subscription,
  SubscriptionStateCode,
  SubscriptionPeriodStatus,
} from "@/types/api";

export function usePlatformPlans() {
  return useQuery({
    queryKey: ["platform-plans"],
    queryFn: async () => {
      const { data } = await platformApi.get<{ data: Plan[] }>("/platform/plans");
      return data.data;
    },
  });
}

export interface CreatePlanInput {
  code: string;
  name: string;
  monthly_price_fcfa?: number | null;
  annual_price_fcfa?: number | null;
}

export function useCreatePlatformPlan() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreatePlanInput) => {
      const { data } = await platformApi.post<{ data: Plan }>("/platform/plans", input);
      return data.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["platform-plans"] }),
  });
}

export type UpdatePlanInput = Partial<Omit<CreatePlanInput, "code">> & { is_active?: boolean };

/** Le code d'une formule n'est jamais modifiable (identifiant stable du journal). */
export function useUpdatePlatformPlan() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, ...input }: UpdatePlanInput & { id: number }) => {
      const { data } = await platformApi.patch<{ data: Plan }>(`/platform/plans/${id}`, input);
      return data.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["platform-plans"] }),
  });
}

export interface StructureSubscriptions {
  periods: Subscription[];
  /** État courant calculé par le backend, jamais recalculé ici. */
  currentState: SubscriptionStateCode;
  currentSubscriptionId: number | null;
}

export function usePlatformStructureSubscriptions(structureId: number | undefined) {
  return useQuery({
    queryKey: ["platform-subscriptions", structureId],
    queryFn: async (): Promise<StructureSubscriptions> => {
      const { data } = await platformApi.get<{
        data: Subscription[];
        current: { state: SubscriptionStateCode; subscription_id: number | null };
      }>(`/platform/structures/${structureId}/subscriptions`);
      return {
        periods: data.data,
        currentState: data.current.state,
        currentSubscriptionId: data.current.subscription_id,
      };
    },
    enabled: Boolean(structureId),
  });
}

export interface CreateSubscriptionInput {
  plan_id: number;
  starts_at: string;
  ends_at: string;
  status: Extract<SubscriptionPeriodStatus, "essai" | "active">;
  notes?: string;
}

export function useCreatePlatformSubscription(structureId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreateSubscriptionInput) => {
      const { data } = await platformApi.post<{ data: Subscription }>(
        `/platform/structures/${structureId}/subscriptions`,
        input,
      );
      return data.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["platform-subscriptions", structureId] }),
  });
}

export function useTogglePlatformSubscription(structureId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async ({ id, action }: { id: number; action: "suspend" | "resume" }) => {
      const { data } = await platformApi.post<{ data: Subscription }>(`/platform/subscriptions/${id}/${action}`);
      return data.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["platform-subscriptions", structureId] }),
  });
}

export function usePlatformPaymentTransactions(structureId: number | undefined) {
  return useQuery({
    queryKey: ["platform-payment-transactions", structureId],
    queryFn: async () => {
      const { data } = await platformApi.get<{ data: PaymentTransaction[] }>(
        `/platform/structures/${structureId}/payment-transactions`,
      );
      return data.data;
    },
    enabled: Boolean(structureId),
  });
}

/** Le montant n'est jamais envoyé : le backend le lit dans la grille des formules. */
export function useCreateDexPayCheckout(structureId: number) {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: { plan_id: number; period: BillingPeriod }) => {
      const { data } = await platformApi.post<{ data: PaymentTransaction }>(
        `/platform/structures/${structureId}/subscriptions/dexpay-checkout`,
        input,
      );
      return data.data;
    },
    onSettled: () => queryClient.invalidateQueries({ queryKey: ["platform-payment-transactions", structureId] }),
  });
}
