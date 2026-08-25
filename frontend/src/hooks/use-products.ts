import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api";
import type {
  Paginated,
  Product,
  ProductBatch,
  ProductCategorie,
  StockLowThresholdAlert,
  StockMovement,
  StockMovementType,
  StockThreshold,
} from "@/types/api";

export interface ProductFilters {
  categorie?: ProductCategorie;
  actif?: boolean;
}

export function useProducts(filters: ProductFilters = {}) {
  return useQuery({
    queryKey: ["products", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Product>>("/products", {
        params: { categorie: filters.categorie, actif: filters.actif },
      });
      return data;
    },
  });
}

export function useProduct(id: number | undefined) {
  return useQuery({
    queryKey: ["products", id],
    queryFn: async () => {
      const { data } = await api.get<{ data: Product }>(`/products/${id}`);
      return data.data;
    },
    enabled: Boolean(id),
  });
}

export interface ProductBatchFilters {
  productId?: number;
  siteId?: number;
}

export function useProductBatches(filters: ProductBatchFilters = {}) {
  return useQuery({
    queryKey: ["product-batches", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<ProductBatch>>("/product-batches", {
        params: { product_id: filters.productId, site_id: filters.siteId },
      });
      return data;
    },
  });
}

function useInvalidateProducts() {
  const queryClient = useQueryClient();
  return () => {
    queryClient.invalidateQueries({ queryKey: ["products"] });
    queryClient.invalidateQueries({ queryKey: ["product-batches"] });
    queryClient.invalidateQueries({ queryKey: ["stock-movements"] });
    queryClient.invalidateQueries({ queryKey: ["stock-alerts"] });
  };
}

export interface CreateStockMovementInput {
  product_batch_id: number;
  site_id: number;
  destination_site_id?: number | null;
  type: StockMovementType;
  quantite: number;
  motif?: string;
  dispensed_for_type?: "consultation" | "hospitalization";
  dispensed_for_id?: number;
}

export function useCreateStockMovement() {
  const invalidate = useInvalidateProducts();
  return useMutation({
    mutationFn: async (input: CreateStockMovementInput) => {
      const { data } = await api.post<{ data: StockMovement }>("/stock-movements", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useStockMovements(filters: { productBatchId?: number; type?: StockMovementType } = {}) {
  return useQuery({
    queryKey: ["stock-movements", filters],
    queryFn: async () => {
      const { data } = await api.get<Paginated<StockMovement>>("/stock-movements", {
        params: { product_batch_id: filters.productBatchId, type: filters.type },
      });
      return data;
    },
  });
}

export function useStockThresholds() {
  return useQuery({
    queryKey: ["stock-thresholds"],
    queryFn: async () => {
      const { data } = await api.get<Paginated<StockThreshold>>("/stock-thresholds");
      return data;
    },
  });
}

export interface CreateStockThresholdInput {
  product_id: number;
  site_id: number;
  seuil_minimum: number;
}

export function useCreateStockThreshold() {
  const invalidate = useInvalidateProducts();
  return useMutation({
    mutationFn: async (input: CreateStockThresholdInput) => {
      const { data } = await api.post<{ data: StockThreshold }>("/stock-thresholds", input);
      return data.data;
    },
    onSuccess: invalidate,
  });
}

export function useLowThresholdAlerts(siteId?: number) {
  return useQuery({
    queryKey: ["stock-alerts", "low-threshold", siteId],
    queryFn: async () => {
      const { data } = await api.get<{ data: StockLowThresholdAlert[] }>("/stock/alerts/low-threshold", {
        params: { site_id: siteId },
      });
      return data.data;
    },
    refetchInterval: 30_000,
  });
}

export function useExpiryAlerts(windowDays: number, siteId?: number) {
  return useQuery({
    queryKey: ["stock-alerts", "expiry", windowDays, siteId],
    queryFn: async () => {
      const { data } = await api.get<{ data: ProductBatch[] }>("/stock/alerts/expiry", {
        params: { window: windowDays, site_id: siteId },
      });
      return data.data;
    },
    refetchInterval: 30_000,
  });
}
