import { useQuery } from "@tanstack/react-query";
import { patientApi } from "@/lib/patient-api";
import type { Invoice, Paginated } from "@/types/api";

async function fetchAllInvoices(): Promise<Invoice[]> {
  const results: Invoice[] = [];
  let page = 1;
  for (;;) {
    const { data } = await patientApi.get<Paginated<Invoice>>("/portail-patient/factures", { params: { page } });
    results.push(...data.data);
    if (!data.links.next) break;
    page++;
  }
  return results;
}

export function usePortalInvoices() {
  return useQuery({
    queryKey: ["portal-invoices"],
    queryFn: fetchAllInvoices,
  });
}

export function usePortalInvoice(id: number) {
  return useQuery({
    queryKey: ["portal-invoice", id],
    queryFn: async () => {
      const { data } = await patientApi.get<{ data: Invoice }>(`/portail-patient/factures/${id}`);
      return data.data;
    },
  });
}

export function usePortalSolde() {
  return useQuery({
    queryKey: ["portal-solde"],
    queryFn: async () => {
      const { data } = await patientApi.get<{ solde: number }>("/portail-patient/solde");
      return data.solde;
    },
  });
}
