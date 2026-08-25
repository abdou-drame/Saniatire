import { useQuery } from "@tanstack/react-query";
import { useMemo } from "react";
import { api } from "@/lib/api";
import type { Paginated, Patient } from "@/types/api";

/**
 * No backend endpoint returns patient identity embedded in queue/consultation
 * rows (only patient_id) and there's no "fetch by ids" endpoint either — so
 * this pulls the structure's patient list once and exposes an id -> Patient
 * lookup for the UI to join client-side. Backend paginates at 15/page with
 * no override param, so this only covers the first page; fine for the
 * current demo dataset, but flagged as a known limit for larger structures.
 */
export function usePatientsDirectory() {
  const query = useQuery({
    queryKey: ["patients", "directory"],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Patient>>("/patients");
      return data.data;
    },
    staleTime: 60_000,
  });

  const byId = useMemo(() => {
    const map = new Map<number, Patient>();
    for (const patient of query.data ?? []) {
      map.set(patient.id, patient);
    }
    return map;
  }, [query.data]);

  return { ...query, byId };
}
