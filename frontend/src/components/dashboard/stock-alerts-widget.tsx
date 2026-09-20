import { PackageX } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { useLowThresholdStockAlerts } from "@/hooks/use-stock-alerts";
import { apiErrorMessage } from "@/lib/api-error";
import type { StockLowThresholdAlert } from "@/types/api";

export function StockAlertsWidget() {
  const navigate = useNavigate();
  const query = useLowThresholdStockAlerts();

  const columns: DataTableColumn<StockLowThresholdAlert>[] = [
    { key: "product", header: "Produit", accessor: (row) => row.product?.nom_commercial ?? `Produit #${row.product_id}` },
    { key: "site", header: "Site", accessor: (row) => row.site?.name ?? `Site #${row.site_id}` },
    { key: "stock_actuel", header: "Stock actuel", align: "right", accessor: (row) => row.stock_actuel },
    { key: "seuil_minimum", header: "Seuil", align: "right", accessor: (row) => row.seuil_minimum },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Alertes de stock</CardTitle>
        <Button variant="ghost" size="sm" onClick={() => navigate("/pharmacie")}>
          Voir le stock
        </Button>
      </CardHeader>
      <CardContent>
        {query.isError ? (
          <ErrorState message={apiErrorMessage(query.error)} onRetry={() => query.refetch()} />
        ) : (
          <DataTable
            columns={columns}
            data={query.data ?? []}
            rowKey={(row) => `${row.product_id}-${row.site_id}`}
            isLoading={query.isLoading}
            emptyState={<EmptyState icon={PackageX} title="Aucune alerte de stock" description="Tous les stocks sont au-dessus du seuil minimum." />}
          />
        )}
      </CardContent>
    </Card>
  );
}
