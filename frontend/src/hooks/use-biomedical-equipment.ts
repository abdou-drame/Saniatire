import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type {
  BiomedicalEquipment,
  BiomedicalEquipmentStatut,
  EquipmentMaintenance,
  MaintenanceType,
  Paginated,
} from "@/types/api";

function useInvalidateEquipements() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: ["biomedical-equipment"] });
    queryClient.invalidateQueries({ queryKey: ["equipment-maintenances"] });
  };
}

export function useEquipments(filters: { siteId?: number; statut?: BiomedicalEquipmentStatut } = {}) {
  return useQuery({
    queryKey: ["biomedical-equipment", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<BiomedicalEquipment>>("/biomedical-equipment", {
        params: { site_id: filters.siteId, statut: filters.statut },
      });
      return data;
    },
  });
}

export function useEquipment(id: number | undefined) {
  return useQuery({
    queryKey: ["biomedical-equipment", id],
    queryFn: async () => {
      const { data } = await api.get<{ data: BiomedicalEquipment }>(`/biomedical-equipment/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export interface CreateEquipmentInput {
  site_id: number;
  supplier_id?: number | null;
  nom: string;
  categorie: string;
  numero_serie: string;
  date_acquisition: string;
  date_fin_garantie?: string | null;
}

export function useCreateEquipment() {
  const invalidate = useInvalidateEquipements();
  return useMutation({
    mutationFn: async (input: CreateEquipmentInput) => {
      const { data } = await api.post<{ data: BiomedicalEquipment }>("/biomedical-equipment", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useMaintenances(filters: { biomedicalEquipmentId?: number } = {}) {
  return useQuery({
    queryKey: ["equipment-maintenances", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<EquipmentMaintenance>>("/equipment-maintenances", {
        params: { biomedical_equipment_id: filters.biomedicalEquipmentId },
      });
      return data;
    },
  });
}

export function useUpcomingMaintenances() {
  return useQuery({
    queryKey: ["equipment-maintenances", "upcoming"],
    queryFn: async () => {
      const { data } = await api.get<{ data: EquipmentMaintenance[] }>("/equipment-maintenances/upcoming");
      return data.data;
    },
    refetchInterval: 30_000,
  });
}

export function useOverdueMaintenances() {
  return useQuery({
    queryKey: ["equipment-maintenances", "overdue"],
    queryFn: async () => {
      const { data } = await api.get<{ data: EquipmentMaintenance[] }>("/equipment-maintenances/overdue");
      return data.data;
    },
    refetchInterval: 30_000,
  });
}

export interface CreateMaintenanceInput {
  biomedical_equipment_id: number;
  type: MaintenanceType;
  date_prevue: string;
  date_realisee?: string | null;
  intervenant_user_id?: number | null;
  intervenant_externe?: string | null;
  cout?: number | null;
  description?: string;
  statut?: "planifiee" | "realisee" | "annulee";
}

export function useCreateMaintenance() {
  const invalidate = useInvalidateEquipements();
  return useMutation({
    mutationFn: async (input: CreateMaintenanceInput) => {
      const { data } = await api.post<{ data: EquipmentMaintenance }>("/equipment-maintenances", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
