import { BarChart2, TrendingUp, Package } from "lucide-react";
import { useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { ErrorState } from "@/components/ui/error-state";
import { KpiCard } from "@/components/ui/kpi-card";
import { KpiRowSkeleton, TableSkeleton } from "@/components/ui/loading-state";
import { Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import {
  useRapportTopProduits,
  useRapportValorisation,
  useRapportVentes,
} from "@/hooks/use-pharmacie-rapports";
import { useSites } from "@/hooks/use-sites";
import { apiErrorMessage } from "@/lib/api-error";
import type { RapportTopProduitLigne, RapportValorisationLigne, RapportVenteLigne } from "@/types/api";

type Onglet = "ventes" | "valorisation" | "top-produits";

export function PharmacieRapportsSection() {
  const [onglet, setOnglet] = useState<Onglet>("ventes");
  const [dateDebut, setDateDebut] = useState("");
  const [dateFin, setDateFin] = useState("");
  const [siteId, setSiteId] = useState<number | undefined>(undefined);
  const [tri, setTri] = useState<"quantite" | "chiffre_affaires">("chiffre_affaires");

  const sitesQuery = useSites();

  const ventesQuery = useRapportVentes(
    onglet === "ventes"
      ? { date_debut: dateDebut || undefined, date_fin: dateFin || undefined, site_id: siteId }
      : {},
  );

  const valorisationQuery = useRapportValorisation(onglet === "valorisation" ? { site_id: siteId } : {});

  const topProduitsQuery = useRapportTopProduits(
    onglet === "top-produits"
      ? { date_debut: dateDebut || undefined, date_fin: dateFin || undefined, tri, limite: 20 }
      : {},
  );

  const fmt = (n: number) => n.toLocaleString("fr-FR", { minimumFractionDigits: 0, maximumFractionDigits: 2 });
  const pct = (n: number) => `${n.toFixed(1)} %`;

  const venteColumns: DataTableColumn<RapportVenteLigne>[] = [
    { key: "date", header: "Date", render: (r) => r.date },
    { key: "nom_commercial", header: "Produit", render: (r) => r.nom_commercial ?? "—" },
    { key: "dci", header: "DCI", render: (r) => r.dci ?? "—" },
    { key: "site", header: "Site", render: (r) => r.site ?? "—" },
    { key: "quantite", header: "Qté", align: "right", render: (r) => r.quantite },
    { key: "montant_vente", header: "Vente", align: "right", render: (r) => fmt(r.montant_vente) },
    { key: "montant_achat", header: "Coût", align: "right", render: (r) => fmt(r.montant_achat) },
    { key: "marge", header: "Marge", align: "right", render: (r) => fmt(r.marge) },
  ];

  const valorisationColumns: DataTableColumn<RapportValorisationLigne>[] = [
    { key: "nom_commercial", header: "Produit", render: (r) => r.nom_commercial },
    { key: "dci", header: "DCI", render: (r) => r.dci ?? "—" },
    { key: "site_name", header: "Site", render: (r) => r.site_name ?? "—" },
    { key: "stock_total", header: "Stock", align: "right", render: (r) => r.stock_total },
    { key: "prix_achat_moyen", header: "P. Achat moy.", align: "right", render: (r) => fmt(r.prix_achat_moyen) },
    { key: "prix_vente_unitaire", header: "P. Vente", align: "right", render: (r) => fmt(r.prix_vente_unitaire) },
    { key: "valeur_achat", header: "Val. Achat", align: "right", render: (r) => fmt(r.valeur_achat) },
    { key: "valeur_vente", header: "Val. Vente", align: "right", render: (r) => fmt(r.valeur_vente) },
  ];

  const topColumns: DataTableColumn<RapportTopProduitLigne>[] = [
    { key: "nom_commercial", header: "Produit", render: (r) => r.nom_commercial },
    { key: "dci", header: "DCI", render: (r) => r.dci ?? "—" },
    { key: "total_quantite", header: "Quantité vendue", align: "right", render: (r) => r.total_quantite },
    { key: "chiffre_affaires", header: "CA", align: "right", render: (r) => fmt(r.chiffre_affaires) },
    { key: "marge_totale", header: "Marge totale", align: "right", render: (r) => fmt(r.marge_totale) },
  ];

  return (
    <div className="space-y-4">
      {/* Onglets */}
      <div className="flex gap-2 border-b border-border pb-2">
        {(
          [
            { key: "ventes", label: "Ventes", icon: TrendingUp },
            { key: "valorisation", label: "Valorisation du stock", icon: Package },
            { key: "top-produits", label: "Top produits", icon: BarChart2 },
          ] as const
        ).map(({ key, label, icon: Icon }) => (
          <button
            key={key}
            onClick={() => setOnglet(key)}
            className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium transition-colors ${
              onglet === key
                ? "bg-primary/10 text-primary"
                : "text-text-subtle hover:bg-surface-raised hover:text-text"
            }`}
          >
            <Icon size={14} />
            {label}
          </button>
        ))}
      </div>

      {/* Filtres */}
      <div className="flex flex-wrap gap-3">
        {onglet !== "valorisation" && (
          <>
            <div>
              <Label className="text-xs">Du</Label>
              <Input type="date" value={dateDebut} onChange={(e) => setDateDebut(e.target.value)} className="h-8 text-sm" />
            </div>
            <div>
              <Label className="text-xs">Au</Label>
              <Input type="date" value={dateFin} onChange={(e) => setDateFin(e.target.value)} className="h-8 text-sm" />
            </div>
          </>
        )}
        {onglet !== "top-produits" && (
          <div>
            <Label className="text-xs">Site</Label>
            <Select
              value={siteId?.toString() ?? ""}
              onChange={(e) => setSiteId(e.target.value ? parseInt(e.target.value) : undefined)}
              className="h-8 text-sm"
            >
              <option value="">Tous les sites</option>
              {(sitesQuery.data ?? []).map((s) => (
                <option key={s.id} value={s.id}>
                  {s.name}
                </option>
              ))}
            </Select>
          </div>
        )}
        {onglet === "top-produits" && (
          <div>
            <Label className="text-xs">Trier par</Label>
            <Select
              value={tri}
              onChange={(e) => setTri(e.target.value as "quantite" | "chiffre_affaires")}
              className="h-8 text-sm"
            >
              <option value="chiffre_affaires">Chiffre d'affaires</option>
              <option value="quantite">Quantité</option>
            </Select>
          </div>
        )}
      </div>

      {/* Contenu par onglet */}
      {onglet === "ventes" && (
        <>
          {ventesQuery.isLoading && <KpiRowSkeleton count={5} />}
          {ventesQuery.isError && (
            <ErrorState message={apiErrorMessage(ventesQuery.error)} onRetry={() => ventesQuery.refetch()} />
          )}
          {ventesQuery.data && (
            <>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                <KpiCard label="Quantités vendues" value={ventesQuery.data.totaux.total_quantite} />
                <KpiCard label="CA total" value={fmt(ventesQuery.data.totaux.total_ventes)} />
                <KpiCard label="Coût total" value={fmt(ventesQuery.data.totaux.total_achats)} />
                <KpiCard label="Marge brute" value={fmt(ventesQuery.data.totaux.total_marge)} />
                <KpiCard label="Taux de marge" value={pct(ventesQuery.data.totaux.taux_marge)} />
              </div>
              <Card>
                <CardHeader>
                  <CardTitle className="text-sm">Détail des dispensations facturées</CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                  <DataTable
                    columns={venteColumns}
                    data={ventesQuery.data.data}
                    rowKey={(r) => r.id}
                    emptyState={<p className="p-4 text-sm text-text-subtle">Aucune vente sur cette période.</p>}
                  />
                </CardContent>
              </Card>
            </>
          )}
        </>
      )}

      {onglet === "valorisation" && (
        <>
          {valorisationQuery.isLoading && <KpiRowSkeleton count={3} />}
          {valorisationQuery.isError && (
            <ErrorState
              message={apiErrorMessage(valorisationQuery.error)}
              onRetry={() => valorisationQuery.refetch()}
            />
          )}
          {valorisationQuery.data && (
            <>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <KpiCard label="Valeur stock (achat)" value={fmt(valorisationQuery.data.totaux.valeur_achat_totale)} />
                <KpiCard label="Valeur stock (vente)" value={fmt(valorisationQuery.data.totaux.valeur_vente_totale)} />
                <KpiCard
                  label="Plus-value potentielle"
                  value={fmt(valorisationQuery.data.totaux.plus_value_potentielle)}
                />
              </div>
              <Card>
                <CardHeader>
                  <CardTitle className="text-sm">Stock valorisé par produit</CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                  <DataTable
                    columns={valorisationColumns}
                    data={valorisationQuery.data.data}
                    rowKey={(r) => `${r.product_id}-${r.site_id}`}
                    emptyState={<p className="p-4 text-sm text-text-subtle">Aucun stock en cours.</p>}
                  />
                </CardContent>
              </Card>
            </>
          )}
        </>
      )}

      {onglet === "top-produits" && (
        <>
          {topProduitsQuery.isLoading && <TableSkeleton />}
          {topProduitsQuery.isError && (
            <ErrorState
              message={apiErrorMessage(topProduitsQuery.error)}
              onRetry={() => topProduitsQuery.refetch()}
            />
          )}
          {topProduitsQuery.data && (
            <Card>
              <CardHeader>
                <CardTitle className="text-sm">Top 20 produits</CardTitle>
              </CardHeader>
              <CardContent className="p-0">
                <DataTable
                  columns={topColumns}
                  data={topProduitsQuery.data}
                  rowKey={(r) => r.product_id}
                  emptyState={<p className="p-4 text-sm text-text-subtle">Aucune vente sur cette période.</p>}
                />
              </CardContent>
            </Card>
          )}
        </>
      )}
    </div>
  );
}
