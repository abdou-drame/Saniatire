import { useQuery } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type {
  RapportTopProduitLigne,
  RapportValorisationResponse,
  RapportVentesResponse,
} from "@/types/api";

export interface RapportVentesFilters {
  date_debut?: string;
  date_fin?: string;
  site_id?: number;
}

export function useRapportVentes(filters: RapportVentesFilters = {}) {
  return useQuery({
    queryKey: ["pharmacie-rapports", "ventes", filters],
    queryFn: async () => {
      const { data } = await api.get<RapportVentesResponse>("/stock/rapports/ventes", { params: filters });
      return data;
    },
  });
}

export interface RapportValorisationFilters {
  site_id?: number;
}

export function useRapportValorisation(filters: RapportValorisationFilters = {}) {
  return useQuery({
    queryKey: ["pharmacie-rapports", "valorisation", filters],
    queryFn: async () => {
      const { data } = await api.get<RapportValorisationResponse>("/stock/rapports/valorisation", {
        params: filters,
      });
      return data;
    },
  });
}

export interface RapportTopProduitsFilters {
  date_debut?: string;
  date_fin?: string;
  limite?: number;
  tri?: "quantite" | "chiffre_affaires";
}

export function useRapportTopProduits(filters: RapportTopProduitsFilters = {}) {
  return useQuery({
    queryKey: ["pharmacie-rapports", "top-produits", filters],
    queryFn: async () => {
      const { data } = await api.get<{ data: RapportTopProduitLigne[] }>("/stock/rapports/top-produits", {
        params: filters,
      });
      return data.data;
    },
  });
}
