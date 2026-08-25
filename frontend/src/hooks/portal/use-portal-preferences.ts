import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { patientApi } from "@/lib/patient-api";
import type { NotificationChannel, NotificationPreference } from "@/types/api";

export function usePortalPreferences() {
  return useQuery({
    queryKey: ["portal-preferences"],
    queryFn: async () => {
      const { data } = await patientApi.get<{ data: NotificationPreference }>("/portail-patient/preferences-notification");
      return data.data;
    },
  });
}

export function useUpdatePortalPreferences() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (canaux: NotificationChannel[]) => {
      const { data } = await patientApi.put<{ data: NotificationPreference }>(
        "/portail-patient/preferences-notification",
        { canaux },
      );
      return data.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["portal-preferences"] }),
  });
}
