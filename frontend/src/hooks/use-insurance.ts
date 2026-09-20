import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type {
  BeneficiaireType,
  InsuranceConvention,
  InsuranceConventionCoverageRule,
  InsuranceProvider,
  InsuranceProviderType,
  Paginated,
  PatientInsuranceCoverage,
} from "@/types/api";

function useInvalidateInsurance() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: ["insurance-providers"] });
    queryClient.invalidateQueries({ queryKey: ["insurance-conventions"] });
    queryClient.invalidateQueries({ queryKey: ["patient-insurance-coverages"] });
  };
}

export function useInsuranceProviders() {
  return useQuery({
    queryKey: ["insurance-providers"],
    queryFn: async () => {
      const { data } = await api.get<Paginated<InsuranceProvider>>("/insurance-providers");
      return data;
    },
  });
}

/**
 * Il n'existe pas de filtre serveur `insurance_provider_id` documenté sur
 * `GET /insurance-conventions` — on récupère donc la liste complète (déjà
 * eager-loadée avec `coverageRules`) et on filtre côté client sur
 * `providerId` quand il est fourni. Simple et suffisant vu le faible volume
 * attendu de conventions par structure.
 */
export function useInsuranceConventions(providerId?: number) {
  return useQuery({
    queryKey: ["insurance-conventions"],
    queryFn: async () => {
      const { data } = await api.get<Paginated<InsuranceConvention>>("/insurance-conventions");
      return data;
    },
    select: (data) =>
      providerId
        ? { ...data, data: data.data.filter((convention) => convention.insurance_provider_id === providerId) }
        : data,
  });
}

export function usePatientInsuranceCoverages(patientId?: number) {
  return useQuery({
    queryKey: ["patient-insurance-coverages", patientId],
    queryFn: async () => {
      const { data } = await api.get<Paginated<PatientInsuranceCoverage>>("/patient-insurance-coverages", {
        params: { patient_id: patientId },
      });
      return data;
    },
    enabled: Boolean(patientId),
  });
}

export interface CreateInsuranceProviderInput {
  nom: string;
  type: InsuranceProviderType;
  contact?: string | null;
}

export function useCreateInsuranceProvider() {
  const invalidate = useInvalidateInsurance();
  return useMutation({
    mutationFn: async (input: CreateInsuranceProviderInput) => {
      const { data } = await api.post<{ data: InsuranceProvider }>("/insurance-providers", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export interface UpdateInsuranceProviderInput {
  id: number;
  nom?: string;
  type?: InsuranceProviderType;
  contact?: string | null;
}

export function useUpdateInsuranceProvider() {
  const invalidate = useInvalidateInsurance();
  return useMutation({
    mutationFn: async ({ id, ...input }: UpdateInsuranceProviderInput) => {
      const { data } = await api.patch<{ data: InsuranceProvider }>(`/insurance-providers/${id}`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export interface CreateInsuranceConventionInput {
  insurance_provider_id: number;
  nom: string;
  date_debut: string;
  date_fin?: string | null;
  actif?: boolean;
}

export function useCreateInsuranceConvention() {
  const invalidate = useInvalidateInsurance();
  return useMutation({
    mutationFn: async (input: CreateInsuranceConventionInput) => {
      const { data } = await api.post<{ data: InsuranceConvention }>("/insurance-conventions", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export interface UpdateInsuranceConventionInput {
  id: number;
  nom?: string;
  date_debut?: string;
  date_fin?: string | null;
  actif?: boolean;
}

export function useUpdateInsuranceConvention() {
  const invalidate = useInvalidateInsurance();
  return useMutation({
    mutationFn: async ({ id, ...input }: UpdateInsuranceConventionInput) => {
      const { data } = await api.patch<{ data: InsuranceConvention }>(`/insurance-conventions/${id}`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export interface CreateCoverageRuleInput {
  conventionId: number;
  categorie: string;
  taux_couverture: number;
  plafond_montant?: number | null;
  exclu?: boolean;
}

export function useCreateCoverageRule() {
  const invalidate = useInvalidateInsurance();
  return useMutation({
    mutationFn: async ({ conventionId, ...input }: CreateCoverageRuleInput) => {
      const { data } = await api.post<{ data: InsuranceConventionCoverageRule }>(
        `/insurance-conventions/${conventionId}/coverage-rules`,
        input,
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export interface UpdateCoverageRuleInput {
  id: number;
  categorie?: string;
  taux_couverture?: number;
  plafond_montant?: number | null;
  exclu?: boolean;
}

export function useUpdateCoverageRule() {
  const invalidate = useInvalidateInsurance();
  return useMutation({
    mutationFn: async ({ id, ...input }: UpdateCoverageRuleInput) => {
      const { data } = await api.patch<{ data: InsuranceConventionCoverageRule }>(
        `/insurance-convention-coverage-rules/${id}`,
        input,
      );
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useDeleteCoverageRule() {
  const invalidate = useInvalidateInsurance();
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/insurance-convention-coverage-rules/${id}`);
    },
    onSuccess: invalidate,
  });
}

export interface CreatePatientInsuranceCoverageInput {
  patient_id: number;
  insurance_convention_id: number;
  numero_adherent: string;
  beneficiaire_type: BeneficiaireType;
  date_debut: string;
  date_fin?: string | null;
  actif?: boolean;
}

export function useCreatePatientInsuranceCoverage() {
  const invalidate = useInvalidateInsurance();
  return useMutation({
    mutationFn: async (input: CreatePatientInsuranceCoverageInput) => {
      const { data } = await api.post<{ data: PatientInsuranceCoverage }>("/patient-insurance-coverages", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export interface UpdatePatientInsuranceCoverageInput {
  id: number;
  numero_adherent?: string;
  beneficiaire_type?: BeneficiaireType;
  date_debut?: string;
  date_fin?: string | null;
  actif?: boolean;
}

export function useUpdatePatientInsuranceCoverage() {
  const invalidate = useInvalidateInsurance();
  return useMutation({
    mutationFn: async ({ id, ...input }: UpdatePatientInsuranceCoverageInput) => {
      const { data } = await api.patch<{ data: PatientInsuranceCoverage }>(`/patient-insurance-coverages/${id}`, input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}
