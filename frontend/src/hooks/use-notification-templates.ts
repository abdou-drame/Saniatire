import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type { NotificationCanal, NotificationTemplate, Paginated } from "@/types/api";

function useInvalidateNotificationTemplates() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: ["notification-templates"] });
  };
}

export interface NotificationTemplateFilters {
  type_evenement?: string;
  canal?: NotificationCanal;
}

export function useNotificationTemplates(filters: NotificationTemplateFilters = {}) {
  return useQuery({
    queryKey: ["notification-templates", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<NotificationTemplate>>("/notification-templates", {
        params: { type_evenement: filters.type_evenement, canal: filters.canal },
      });
      return data;
    },
  });
}

export interface NotificationTemplateInput {
  type_evenement: string;
  canal: NotificationCanal;
  sujet?: string | null;
  contenu: string;
  actif?: boolean;
}

// structure_id n'est jamais envoyé : forcé côté serveur depuis l'utilisateur
// authentifié (cf. NotificationTemplateController::store).
export function useCreateNotificationTemplate() {
  const invalidate = useInvalidateNotificationTemplates();
  return useMutation({
    mutationFn: async (input: NotificationTemplateInput) => {
      const { data } = await api.post<{ data: NotificationTemplate }>("/notification-templates", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useUpdateNotificationTemplate() {
  const invalidate = useInvalidateNotificationTemplates();
  return useMutation({
    mutationFn: async ({ id, ...input }: { id: number } & Partial<NotificationTemplateInput>) => {
      const { data } = await api.patch<{ data: NotificationTemplate }>(`/notification-templates/${id}`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useDeleteNotificationTemplate() {
  const invalidate = useInvalidateNotificationTemplates();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/notification-templates/${id}`);
    },
    onSuccess: invalidate,
  });
}
