import { FileWarning } from "lucide-react";
import { useMemo, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { PatientPicker } from "@/components/clinical/patient-picker";
import { useAuth } from "@/hooks/use-auth";
import { useBalanceAgee } from "@/hooks/use-creances";
import { useInsuranceProviders } from "@/hooks/use-insurance";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import { cn } from "@/lib/utils";
import { BUCKET_BADGE, BUCKET_COLOR_CLASS, BUCKET_LABEL } from "@/pages/creances/creances-status";
import type { BalanceAgeeLigne, CreancesBucket, Patient } from "@/types/api";

const BUCKET_ORDER: CreancesBucket[] = ["0-30", "31-60", "61-90", "90+"];

/**
 * Écran Créances (balance âgée). Le backend (CreancesController::balanceAgee)
 * est seul décideur du contenu de `buckets` et `lignes` — cet écran ne fait
 * qu'afficher tel quel ce que l'API renvoie (aucun bucket ni aucun solde
 * n'est recalculé ou agrégé ici) et gate l'accès sur `facturation.creances`,
 * une permission distincte de `facturation.view`/`assurance.view`.
 */
export function CreancesPage() {
  const { hasPermission } = useAuth();
  const canView = hasPermission("facturation.creances");

  const [patient, setPatient] = useState<Patient | null>(null);
  const [conventionId, setConventionId] = useState<number | null>(null);

  const providersQuery = useInsuranceProviders();

  const conventionOptions = useMemo(() => {
    const providers = providersQuery.data?.data ?? [];
    return providers.flatMap((provider) =>
      (provider.conventions ?? []).map((convention) => ({
        id: convention.id,
        label: `${provider.nom} — ${convention.nom}`,
      })),
    );
  }, [providersQuery.data]);

  const balanceQuery = useBalanceAgee({
    patient_id: patient?.id,
    insurance_convention_id: conventionId ?? undefined,
  });

  const columns: DataTableColumn<BalanceAgeeLigne>[] = [
    { key: "numero", header: "N° facture", accessor: (row) => row.numero, sortable: true },
    { key: "patient", header: "Patient", render: (row) => row.patient_label ?? "—" },
    {
      key: "assureur",
      header: "Organisme assureur",
      render: (row) => row.insurance_provider_label ?? "Sans assurance",
    },
    {
      key: "date_emission",
      header: "Date d'émission",
      accessor: (row) => row.date_emission,
      render: (row) => formatDate(row.date_emission),
      sortable: true,
    },
    {
      key: "anciennete",
      header: "Ancienneté (j)",
      align: "right",
      accessor: (row) => row.anciennete_jours,
      sortable: true,
    },
    {
      key: "solde",
      header: "Solde",
      align: "right",
      accessor: (row) => row.solde,
      render: (row) => Number(row.solde).toLocaleString("fr-FR"),
      sortable: true,
    },
    {
      key: "bucket",
      header: "Bucket",
      render: (row) => <Badge status={BUCKET_BADGE[row.bucket]}>{BUCKET_LABEL[row.bucket]}</Badge>,
    },
  ];

  if (!canView) {
    return (
      <div className="space-y-6">
        <div>
          <h1 className="font-heading text-xl font-semibold text-text">Créances</h1>
        </div>
        <ErrorState message="Vous n'avez pas les permissions nécessaires pour accéder à ces données." />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-heading text-xl font-semibold text-text">Créances</h1>
        <p className="mt-1 text-sm text-text-muted">
          Balance âgée des factures non soldées, calculée par le serveur — ancienneté et soldes affichés tels quels.
        </p>
      </div>

      {balanceQuery.isError ? (
        <ErrorState message={apiErrorMessage(balanceQuery.error)} onRetry={() => balanceQuery.refetch()} />
      ) : (
        <>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            {BUCKET_ORDER.map((bucket) => (
              <Card key={bucket} className="p-5">
                <span className="text-xs font-medium uppercase tracking-wide text-text-subtle">
                  {BUCKET_LABEL[bucket]}
                </span>
                <div className="mt-3">
                  <span className={cn("font-heading font-tabular text-2xl font-semibold", BUCKET_COLOR_CLASS[bucket])}>
                    {balanceQuery.isLoading || !balanceQuery.data
                      ? "—"
                      : Number(balanceQuery.data.buckets[bucket]).toLocaleString("fr-FR")}
                  </span>
                </div>
              </Card>
            ))}
          </div>

          <Card>
            <CardHeader>
              <CardTitle>Filtres</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                  <Label>Patient</Label>
                  <PatientPicker value={patient} onChange={setPatient} placeholder="Tous les patients..." />
                </div>
                <div>
                  <Label>Organisme assureur</Label>
                  <Select
                    value={conventionId ?? ""}
                    onChange={(e) => setConventionId(e.target.value ? Number(e.target.value) : null)}
                    disabled={providersQuery.isLoading}
                  >
                    <option value="">Tous les organismes</option>
                    {conventionOptions.map((option) => (
                      <option key={option.id} value={option.id}>
                        {option.label}
                      </option>
                    ))}
                  </Select>
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Factures en souffrance</CardTitle>
            </CardHeader>
            <CardContent>
              <DataTable
                columns={columns}
                data={balanceQuery.data?.lignes ?? []}
                rowKey={(row) => row.invoice_id}
                isLoading={balanceQuery.isLoading}
                emptyState={
                  <EmptyState
                    icon={FileWarning}
                    title="Aucune créance"
                    description="Aucune facture en souffrance pour ces filtres."
                  />
                }
              />
            </CardContent>
          </Card>
        </>
      )}
    </div>
  );
}
