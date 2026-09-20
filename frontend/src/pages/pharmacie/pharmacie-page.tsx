import { AlertTriangle, Clock, PackagePlus, PackageX, Pill, Plus } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { KpiCard } from "@/components/ui/kpi-card";
import { KpiRowSkeleton, TableSkeleton } from "@/components/ui/loading-state";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { CreateBatchDialog } from "@/components/pharmacie/create-batch-dialog";
import { CreateProductDialog } from "@/components/pharmacie/create-product-dialog";
import { StockMovementDialog } from "@/components/pharmacie/stock-movement-dialog";
import { StockThresholdDialog } from "@/components/pharmacie/stock-threshold-dialog";
import { useAuth } from "@/hooks/use-auth";
import {
  useExpiryAlerts,
  useLowThresholdAlerts,
  useProduct,
  useProductBatches,
  useProducts,
  useStockThresholds,
} from "@/hooks/use-products";
import { useSites } from "@/hooks/use-sites";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import { PharmacieAlertesSection } from "@/pages/pharmacie/pharmacie-alertes-section";
import { CATEGORIE_BADGE, CATEGORIE_LABEL } from "@/pages/pharmacie/pharmacie-status";
import type { Product, ProductBatch, ProductCategorie, StockThreshold } from "@/types/api";

const CATEGORIE_OPTIONS: ProductCategorie[] = ["medicament", "consommable", "dispositif_medical"];

export function PharmaciePage() {
  const { hasPermission } = useAuth();

  const [categorieFilter, setCategorieFilter] = useState<ProductCategorie | "">("");
  const [selectedProductId, setSelectedProductId] = useState<number | null>(null);
  const [movementDialogOpen, setMovementDialogOpen] = useState(false);
  const [movementDialogProductId, setMovementDialogProductId] = useState<number | undefined>(undefined);
  const [createProductDialogOpen, setCreateProductDialogOpen] = useState(false);
  const [createBatchDialogOpen, setCreateBatchDialogOpen] = useState(false);
  const [thresholdDialogOpen, setThresholdDialogOpen] = useState(false);

  const productsQuery = useProducts({ categorie: categorieFilter || undefined });
  const lowThresholdQuery = useLowThresholdAlerts();
  const nearExpiryQuery = useExpiryAlerts(30);
  const expiredQuery = useExpiryAlerts(3650);

  const productDetailQuery = useProduct(selectedProductId ?? undefined);
  const batchesQuery = useProductBatches({ productId: selectedProductId ?? undefined });
  const productThresholdsQuery = useStockThresholds({
    productId: selectedProductId ?? undefined,
    enabled: Boolean(selectedProductId),
  });
  const sitesQuery = useSites();

  const today = new Date();
  const nearExpiryCount = (nearExpiryQuery.data ?? []).filter(
    (batch) => new Date(batch.date_peremption) >= today,
  ).length;
  const expiredCount = (expiredQuery.data ?? []).filter((batch) => new Date(batch.date_peremption) < today).length;

  const statsLoading = lowThresholdQuery.isLoading || nearExpiryQuery.isLoading || expiredQuery.isLoading;
  const statsError = lowThresholdQuery.error ?? nearExpiryQuery.error ?? expiredQuery.error;

  function openMovementDialog(productId?: number) {
    setMovementDialogProductId(productId);
    setMovementDialogOpen(true);
  }

  const productColumns: DataTableColumn<Product>[] = [
    {
      key: "nom_commercial",
      header: "Nom commercial",
      render: (row) => row.nom_commercial,
    },
    {
      key: "dci",
      header: "DCI",
      render: (row) => row.dci ?? "—",
    },
    {
      key: "categorie",
      header: "Catégorie",
      render: (row) => <Badge status={CATEGORIE_BADGE[row.categorie]}>{CATEGORIE_LABEL[row.categorie]}</Badge>,
    },
    {
      key: "unite_vente",
      header: "Unité de vente",
      render: (row) => row.unite_vente,
    },
    {
      key: "stock_total",
      header: "Stock total",
      align: "right",
      render: (row) => row.stock_total ?? "—",
    },
  ];

  const batchColumns: DataTableColumn<ProductBatch>[] = [
    {
      key: "numero_lot",
      header: "Lot",
      render: (row) => row.numero_lot,
    },
    {
      key: "date_peremption",
      header: "Péremption",
      render: (row) => formatDate(row.date_peremption),
    },
    {
      key: "quantite_stock",
      header: "Quantité",
      align: "right",
      render: (row) => row.quantite_stock,
    },
    {
      key: "site",
      header: "Site",
      render: (row) => row.site?.name ?? "—",
    },
    {
      key: "supplier",
      header: "Fournisseur",
      render: (row) => row.supplier?.nom ?? "—",
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="font-heading text-xl font-semibold text-text">Pharmacie / Stocks</h1>
          <p className="mt-1 text-sm text-text-muted">
            Catalogue produits, lots, mouvements de stock et alertes de péremption/seuil.
          </p>
        </div>
        {hasPermission("stock.dispense") && (
          <Button onClick={() => openMovementDialog(undefined)}>
            <PackagePlus size={16} />
            Nouveau mouvement
          </Button>
        )}
      </div>

      {statsLoading && <KpiRowSkeleton />}
      {statsError && !statsLoading && (
        <ErrorState
          message={apiErrorMessage(statsError)}
          onRetry={() => {
            lowThresholdQuery.refetch();
            nearExpiryQuery.refetch();
            expiredQuery.refetch();
          }}
        />
      )}
      {!statsLoading && !statsError && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <KpiCard label="Produits sous seuil" value={lowThresholdQuery.data?.length ?? 0} icon={AlertTriangle} />
          <KpiCard label="Lots proches péremption" value={nearExpiryCount} icon={Clock} />
          <KpiCard label="Lots périmés" value={expiredCount} icon={PackageX} />
        </div>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Catalogue produits</CardTitle>
          {hasPermission("stock.create") && (
            <Button variant="secondary" size="sm" onClick={() => setCreateProductDialogOpen(true)}>
              <Plus size={14} />
              Nouveau produit
            </Button>
          )}
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="max-w-xs">
            <Label>Catégorie</Label>
            <Select
              value={categorieFilter}
              onChange={(e) => setCategorieFilter(e.target.value as ProductCategorie | "")}
            >
              <option value="">Toutes</option>
              {CATEGORIE_OPTIONS.map((categorie) => (
                <option key={categorie} value={categorie}>
                  {CATEGORIE_LABEL[categorie]}
                </option>
              ))}
            </Select>
          </div>

          {productsQuery.isError ? (
            <ErrorState message={apiErrorMessage(productsQuery.error)} onRetry={() => productsQuery.refetch()} />
          ) : (
            <DataTable
              columns={productColumns}
              data={productsQuery.data?.data ?? []}
              rowKey={(row) => row.id}
              isLoading={productsQuery.isLoading}
              onRowClick={(row) => setSelectedProductId(row.id)}
              emptyState={
                <EmptyState
                  icon={Pill}
                  title="Aucun produit"
                  description="Aucun produit ne correspond à ce filtre."
                />
              }
            />
          )}
        </CardContent>
      </Card>

      {selectedProductId && (
        <Card>
          <CardHeader>
            <CardTitle>Détail produit</CardTitle>
            <div className="flex flex-wrap gap-2">
              {hasPermission("stock.create") && (
                <Button variant="secondary" size="sm" onClick={() => setCreateBatchDialogOpen(true)}>
                  <Plus size={14} />
                  Nouveau lot
                </Button>
              )}
              {hasPermission("stock.dispense") && (
                <Button variant="secondary" size="sm" onClick={() => openMovementDialog(selectedProductId)}>
                  <PackagePlus size={14} />
                  Nouveau mouvement pour ce produit
                </Button>
              )}
              {(hasPermission("stock.create") || hasPermission("stock.update")) && (
                <Button variant="secondary" size="sm" onClick={() => setThresholdDialogOpen(true)}>
                  <AlertTriangle size={14} />
                  Configurer un seuil
                </Button>
              )}
            </div>
          </CardHeader>
          <CardContent className="space-y-4">
            {productDetailQuery.isLoading ? (
              <TableSkeleton columns={4} />
            ) : productDetailQuery.isError ? (
              <ErrorState
                message={apiErrorMessage(productDetailQuery.error)}
                onRetry={() => productDetailQuery.refetch()}
              />
            ) : productDetailQuery.data ? (
              <div className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div>
                  <p className="text-xs text-text-subtle">Nom commercial</p>
                  <p className="text-text">{productDetailQuery.data.nom_commercial}</p>
                </div>
                <div>
                  <p className="text-xs text-text-subtle">DCI</p>
                  <p className="text-text">{productDetailQuery.data.dci ?? "—"}</p>
                </div>
                <div>
                  <p className="text-xs text-text-subtle">Forme galénique</p>
                  <p className="text-text">{productDetailQuery.data.forme_galenique ?? "—"}</p>
                </div>
                <div>
                  <p className="text-xs text-text-subtle">Dosage</p>
                  <p className="text-text">{productDetailQuery.data.dosage ?? "—"}</p>
                </div>
                <div>
                  <p className="text-xs text-text-subtle">Catégorie</p>
                  <Badge status={CATEGORIE_BADGE[productDetailQuery.data.categorie]}>
                    {CATEGORIE_LABEL[productDetailQuery.data.categorie]}
                  </Badge>
                </div>
                <div>
                  <p className="text-xs text-text-subtle">Unité de vente</p>
                  <p className="text-text">{productDetailQuery.data.unite_vente}</p>
                </div>
                <div>
                  <p className="text-xs text-text-subtle">Stock total</p>
                  <p className="text-text">{productDetailQuery.data.stock_total ?? "—"}</p>
                </div>
              </div>
            ) : null}

            {batchesQuery.isError ? (
              <ErrorState message={apiErrorMessage(batchesQuery.error)} onRetry={() => batchesQuery.refetch()} />
            ) : (
              <DataTable
                columns={batchColumns}
                data={batchesQuery.data?.data ?? []}
                rowKey={(row) => row.id}
                isLoading={batchesQuery.isLoading}
                emptyState={
                  <EmptyState icon={PackageX} title="Aucun lot" description="Ce produit n'a aucun lot enregistré." />
                }
              />
            )}

            <div>
              <p className="mb-2 text-xs font-medium uppercase tracking-wide text-text-subtle">
                Seuils d'alerte configurés
              </p>
              {productThresholdsQuery.isError ? (
                <ErrorState
                  message={apiErrorMessage(productThresholdsQuery.error)}
                  onRetry={() => productThresholdsQuery.refetch()}
                />
              ) : (productThresholdsQuery.data?.data ?? []).length === 0 ? (
                <p className="text-sm text-text-subtle">Aucun seuil configuré pour ce produit sur aucun site.</p>
              ) : (
                <ul className="space-y-1.5">
                  {(productThresholdsQuery.data?.data ?? []).map((threshold: StockThreshold) => (
                    <li
                      key={threshold.id}
                      className="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm"
                    >
                      <span className="text-text">
                        {(sitesQuery.data ?? []).find((site) => site.id === threshold.site_id)?.name ??
                          `Site #${threshold.site_id}`}
                      </span>
                      <span className="text-text-muted">Seuil minimum : {threshold.seuil_minimum}</span>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          </CardContent>
        </Card>
      )}

      <PharmacieAlertesSection />

      <StockMovementDialog
        open={movementDialogOpen}
        onOpenChange={setMovementDialogOpen}
        productId={movementDialogProductId}
        onCreated={() => {
          productsQuery.refetch();
          lowThresholdQuery.refetch();
        }}
      />

      <CreateProductDialog
        open={createProductDialogOpen}
        onOpenChange={setCreateProductDialogOpen}
        onCreated={(productId) => {
          productsQuery.refetch();
          setSelectedProductId(productId);
        }}
      />

      {selectedProductId && (
        <CreateBatchDialog
          open={createBatchDialogOpen}
          onOpenChange={setCreateBatchDialogOpen}
          productId={selectedProductId}
          onCreated={() => {
            batchesQuery.refetch();
            productDetailQuery.refetch();
            productsQuery.refetch();
          }}
        />
      )}

      {selectedProductId && (
        <StockThresholdDialog
          open={thresholdDialogOpen}
          onOpenChange={setThresholdDialogOpen}
          productId={selectedProductId}
          onSaved={() => {
            productThresholdsQuery.refetch();
            lowThresholdQuery.refetch();
          }}
        />
      )}
    </div>
  );
}
