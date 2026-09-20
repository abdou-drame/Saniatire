import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { patientApi } from "@/lib/patient-api";
import type { Complaint, Paginated } from "@/types/api";

async function fetchAllComplaints(): Promise<Complaint[]> {
  const results: Complaint[] = [];
  let page = 1;
  for (;;) {
    const { data } = await patientApi.get<Paginated<Complaint>>("/portail-patient/reclamations", { params: { page } });
    results.push(...data.data);
    if (!data.links.next) break;
    page++;
  }
  return results;
}

export function usePortalComplaints() {
  return useQuery({
    queryKey: ["portal-complaints"],
    queryFn: fetchAllComplaints,
  });
}

export function usePortalComplaint(id: number) {
  return useQuery({
    queryKey: ["portal-complaint", id],
    queryFn: async () => {
      const { data } = await patientApi.get<{ data: Complaint }>(`/portail-patient/reclamations/${id}`);
      return data.data;
    },
  });
}

export interface CreatePortalComplaintInput {
  motif: string;
  description: string;
  service_concerne?: string;
}

// patient_id, statut et origin ne sont jamais envoyés ici : tous forcés
// côté serveur (PatientPortalController::storeComplaint).
export function useCreatePortalComplaint() {
  const queryClient = useQueryClient();
  return useMutation({
    mutationFn: async (input: CreatePortalComplaintInput) => {
      const { data } = await patientApi.post<{ data: Complaint }>("/portail-patient/reclamations", input);
      return data.data;
    },
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ["portal-complaints"] }),
  });
}
