import { AlertTriangle, PackageX } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useExpiryAlerts, useLowThresholdAlerts } from "@/hooks/use-products";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import type { ProductBatch, StockLowThresholdAlert } from "@/types/api";

/**
 * "Périmé" vs "Proche péremption" is a purely cosmetic split of the single dataset returned
 * by useExpiryAlerts — the backend does not expose a separate "expired only" endpoint, so
 * both categories are derived client-side from the same real `date_peremption` field, never
 * synthesized or used to gate an action.
 */
export function PharmacieAlertesSection() {
  const [windowDaysInput, setWindowDaysInput] = useState("30");
  const windowDays = Number(windowDaysInput) > 0 ? Number(windowDaysInput) : 30;

  const expiryQuery = useExpiryAlerts(windowDays);
  const lowThresholdQuery = useLowThresholdAlerts();

  const today = new Date();

  const expiryColumns: DataTableColumn<ProductBatch>[] = [
    {
      key: "product",
      header: "Produit",
      render: (row) => row.product?.nom_commercial ?? "—",
    },
    {
      key: "numero_lot",
      header: "Lot",
      render: (row) => row.numero_lot,
    },
    {
      key: "site",
      header: "Site",
      render: (row) => row.site?.name ?? "—",
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
      key: "statut",
      header: "Statut",
      render: (row) =>
        new Date(row.date_peremption) < today ? (
          <Badge status="danger">Périmé</Badge>
        ) : (
          <Badge status="warning">Proche péremption</Badge>
        ),
    },
  ];

  const lowThresholdColumns: DataTableColumn<StockLowThresholdAlert>[] = [
    {
      key: "product",
      header: "Produit",
      render: (row) => row.product?.nom_commercial ?? "—",
    },
    {
      key: "site",
      header: "Site",
      render: (row) => row.site?.name ?? "—",
    },
    {
      key: "stock_actuel",
      header: "Stock actuel",
      align: "right",
      render: (row) => row.stock_actuel,
    },
    {
      key: "seuil_minimum",
      header: "Seuil minimum",
      align: "right",
      render: (row) => row.seuil_minimum,
    },
  ];

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Alertes de péremption</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="max-w-xs">
            <Label>Fenêtre de péremption (jours)</Label>
            <Input
              type="number"
              min={1}
              value={windowDaysInput}
              onChange={(e) => setWindowDaysInput(e.target.value)}
            />
          </div>

          {expiryQuery.isError ? (
            <ErrorState message={apiErrorMessage(expiryQuery.error)} onRetry={() => expiryQuery.refetch()} />
          ) : (
            <DataTable
              columns={expiryColumns}
              data={expiryQuery.data ?? []}
              rowKey={(row) => row.id}
              isLoading={expiryQuery.isLoading}
              emptyState={
                <EmptyState
                  icon={AlertTriangle}
                  title="Aucune alerte de péremption"
                  description="Aucun lot périmé ou proche de la péremption sur cette fenêtre."
                />
              }
            />
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Alertes de seuil bas</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {lowThresholdQuery.isError ? (
            <ErrorState
              message={apiErrorMessage(lowThresholdQuery.error)}
              onRetry={() => lowThresholdQuery.refetch()}
            />
          ) : (
            <DataTable
              columns={lowThresholdColumns}
              data={lowThresholdQuery.data ?? []}
              rowKey={(row) => `${row.product_id}-${row.site_id}`}
              isLoading={lowThresholdQuery.isLoading}
              emptyState={
                <EmptyState
                  icon={PackageX}
                  title="Aucune alerte de seuil"
                  description="Aucun produit n'est actuellement sous son seuil minimum."
                />
              }
            />
          )}
        </CardContent>
      </Card>
    </div>
  );
}
