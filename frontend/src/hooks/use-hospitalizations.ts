import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type {
  Hospitalization,
  HospitalizationDailyNote,
  HospitalizationStats,
  HospitalizationStatus,
  Paginated,
  Ward,
} from "@/types/api";

export interface HospitalizationFilters {
  patientId?: number;
  status?: HospitalizationStatus;
}

export function useHospitalizations(filters: HospitalizationFilters) {
  return useQuery({
    queryKey: ["hospitalizations", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Hospitalization>>("/hospitalizations", {
        params: {
          patient_id: filters.patientId,
          status: filters.status,
        },
      });
      return data;
    },
  });
}

export function useHospitalization(id: number | undefined) {
  return useQuery({
    queryKey: ["hospitalizations", id],
    queryFn: async () => {
      const { data } = await api.get<{ data: Hospitalization }>(`/hospitalizations/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export function useHospitalizationStats() {
  return useQuery({
    queryKey: ["hospitalizations", "stats"],
    queryFn: async () => {
      const { data } = await api.get<{ data: HospitalizationStats }>("/hospitalizations/stats");
      return data.data;
    },
    refetchInterval: 30_000,
  });
}

/**
 * `wards.index` ne charge pas les lits (pour rester léger en liste) alors
 * que `wards.show` les charge intégralement — on assemble donc la vue par
 * service en récupérant chaque ward en détail après la liste.
 */
export function useWardsWithBeds() {
  return useQuery({
    queryKey: ["wards", "with-beds"],
    queryFn: async () => {
      const { data: wardsPage } = await api.get<Paginated<Ward>>("/wards");
      const wards = await Promise.all(
        wardsPage.data.map(async (ward) => {
          const { data } = await api.get<{ data: Ward }>(`/wards/${ward.id}`);
          return data.data;
        }),
      );
      return wards;
    },
  });
}

export interface AdmitPatientInput {
  site_id: number;
  patient_id: number;
  bed_id: number;
  attending_physician_id: number;
  admission_reason: string;
}

export interface AddDailyNoteInput {
  hospitalizationId: number;
  note_date?: string;
  care_administered?: string;
  medications_given?: string;
  procedures_performed?: string;
  observations?: string;
}

export interface DischargePatientInput {
  hospitalizationId: number;
  discharge_summary: string;
}

function useInvalidateHospitalizations() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: ["hospitalizations"] });
    queryClient.invalidateQueries({ queryKey: ["wards"] });
  };
}

export function useAdmitPatient() {
  const invalidate = useInvalidateHospitalizations();
  return useMutation({
    mutationFn: async (input: AdmitPatientInput) => {
      const { data } = await api.post<{ data: Hospitalization }>("/hospitalizations", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useAddDailyNote() {
  const invalidate = useInvalidateHospitalizations();
  return useMutation({
    mutationFn: async ({ hospitalizationId, ...input }: AddDailyNoteInput) => {
      const { data } = await api.post<{ data: HospitalizationDailyNote }>(
        `/hospitalizations/${hospitalizationId}/daily-notes`,
        input,
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useDischargePatient() {
  const invalidate = useInvalidateHospitalizations();
  return useMutation({
    mutationFn: async ({ hospitalizationId, ...input }: DischargePatientInput) => {
      const { data } = await api.post<{ data: Hospitalization }>(
        `/hospitalizations/${hospitalizationId}/discharge`,
        input,
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}
