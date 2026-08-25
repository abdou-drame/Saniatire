import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type {
  Paginated,
  SurgicalChecklist,
  SurgicalChecklistStep,
  SurgicalProcedure,
  SurgicalProcedureStatus,
} from "@/types/api";

export interface SurgicalProcedureFilters {
  patientId?: number;
  status?: SurgicalProcedureStatus;
}

export function useSurgicalProcedures(filters: SurgicalProcedureFilters) {
  return useQuery({
    queryKey: ["surgical-procedures", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<SurgicalProcedure>>("/surgical-procedures", {
        params: {
          patient_id: filters.patientId,
          status: filters.status,
        },
      });
      return data;
    },
  });
}

export function useSurgicalProcedure(id: number | undefined) {
  return useQuery({
    queryKey: ["surgical-procedures", id],
    queryFn: async () => {
      const { data } = await api.get<{ data: SurgicalProcedure }>(`/surgical-procedures/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export interface PlanSurgicalProcedureInput {
  site_id: number;
  patient_id: number;
  hospitalization_id?: number | null;
  surgeon_id: number;
  anesthesiologist_id: number;
  operating_room: string;
  procedure_type: string;
  scheduled_at: string;
}

export interface ValidateChecklistStepInput {
  surgicalProcedureId: number;
  step: SurgicalChecklistStep;
  items: string[];
}

function useInvalidateSurgicalProcedures() {
  const queryClient = useQueryClient();
  return () => queryClient.invalidateQueries({ queryKey: ["surgical-procedures"] });
}

export function usePlanSurgicalProcedure() {
  const invalidate = useInvalidateSurgicalProcedures();
  return useMutation({
    mutationFn: async (input: PlanSurgicalProcedureInput) => {
      const { data } = await api.post<{ data: SurgicalProcedure }>("/surgical-procedures", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useStartSurgicalProcedure() {
  const invalidate = useInvalidateSurgicalProcedures();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: SurgicalProcedure }>(`/surgical-procedures/${id}/start`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useValidateChecklistStep() {
  const invalidate = useInvalidateSurgicalProcedures();
  return useMutation({
    mutationFn: async ({ surgicalProcedureId, step, items }: ValidateChecklistStepInput) => {
      const { data } = await api.post<{ data: SurgicalChecklist }>(
        `/surgical-procedures/${surgicalProcedureId}/checklist/${step}`,
        { items },
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}

/**
 * Ne masque/désactive jamais l'appel de cette mutation en fonction d'un
 * état de checklist perçu côté client — le backend est seul juge (422
 * "Les 3 étapes de la checklist doivent être validées avant de terminer
 * l'intervention." si elle est incomplète), à afficher verbatim.
 */
export function useCompleteSurgicalProcedure() {
  const invalidate = useInvalidateSurgicalProcedures();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: SurgicalProcedure }>(`/surgical-procedures/${id}/complete`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useCancelSurgicalProcedure() {
  const invalidate = useInvalidateSurgicalProcedures();
  return useMutation({
    mutationFn: async (id: number) => {
      const { data } = await api.post<{ data: SurgicalProcedure }>(`/surgical-procedures/${id}/cancel`);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
