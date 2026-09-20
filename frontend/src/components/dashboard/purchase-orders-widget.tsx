import { ShoppingCart } from "lucide-react";
import { useNavigate } from "react-router-dom";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { usePurchaseOrders } from "@/hooks/use-purchase-orders";
import { apiErrorMessage } from "@/lib/api-error";
import { formatFcfa } from "@/lib/format";
import type { PurchaseOrder } from "@/types/api";

export function PurchaseOrdersWidget() {
  const navigate = useNavigate();
  const query = usePurchaseOrders({ statut: "en_attente_validation" });

  const columns: DataTableColumn<PurchaseOrder>[] = [
    { key: "id", header: "N°", align: "right", accessor: (row) => row.id },
    { key: "supplier", header: "Fournisseur", accessor: (row) => row.supplier?.nom ?? `Fournisseur #${row.supplier_id}` },
    { key: "site", header: "Site", accessor: (row) => row.site?.name ?? `Site #${row.site_id}` },
    { key: "montant_total", header: "Montant", align: "right", accessor: (row) => formatFcfa(row.montant_total) },
    { key: "statut", header: "Statut", render: () => <Badge status="warning">En attente</Badge> },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Commandes en attente de validation</CardTitle>
        <Button variant="ghost" size="sm" onClick={() => navigate("/achats")}>
          Traiter
        </Button>
      </CardHeader>
      <CardContent>
        {query.isError ? (
          <ErrorState message={apiErrorMessage(query.error)} onRetry={() => query.refetch()} />
        ) : (
          <DataTable
            columns={columns}
            data={query.data?.data ?? []}
            rowKey={(row) => row.id}
            isLoading={query.isLoading}
            onRowClick={() => navigate("/achats")}
            emptyState={<EmptyState icon={ShoppingCart} title="Aucune commande en attente" />}
          />
        )}
      </CardContent>
    </Card>
  );
}
