import { Lock, Plus, Users } from "lucide-react";
import { useMemo, useState } from "react";
import { useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useAuth } from "@/hooks/use-auth";
import { usePatients } from "@/hooks/use-patients";
import { apiErrorMessage } from "@/lib/api-error";
import { age, formatDate } from "@/lib/datetime";
import { PatientQuickCreateDialog } from "@/pages/reception/patient-quick-create-dialog";
import type { Patient } from "@/types/api";

/**
 * Répertoire patients — liste complète et parcourable, indépendante de tout
 * rendez-vous (contrairement à la recherche rapide de la topbar ou à la
 * file d'attente). Ouvre la fiche patient existante (/patients/:id) telle
 * quelle : aucune vue alternative n'est construite ici.
 */
export function PatientsDirectoryPage() {
  const { hasPermission } = useAuth();
  const canCreate = hasPermission("patients.create");
  const navigate = useNavigate();

  const patientsQuery = usePatients();
  const [search, setSearch] = useState("");
  const [createOpen, setCreateOpen] = useState(false);

  const filtered = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (!term) return patientsQuery.data ?? [];
    return (patientsQuery.data ?? []).filter((patient) => {
      const haystack = `${patient.first_name} ${patient.last_name} ${patient.patient_number} ${patient.phone ?? ""}`.toLowerCase();
      return haystack.includes(term);
    });
  }, [patientsQuery.data, search]);

  const columns: DataTableColumn<Patient>[] = [
    { key: "patient_number", header: "Identifiant", render: (row) => <span className="font-tabular">{row.patient_number}</span> },
    {
      key: "name",
      header: "Nom",
      render: (row) => (
        <span className="font-medium text-text">
          {row.last_name} {row.first_name}
        </span>
      ),
    },
    {
      key: "birth_date",
      header: "Date de naissance",
      render: (row) => `${formatDate(row.birth_date)} (${age(row.birth_date)} ans)`,
    },
    { key: "phone", header: "Téléphone", render: (row) => row.phone ?? "—" },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="font-heading text-xl font-semibold text-text">Patients</h1>
          <p className="mt-1 text-sm text-text-muted">Répertoire complet des patients de la structure.</p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Répertoire</CardTitle>
          {canCreate && (
            <Button size="sm" onClick={() => setCreateOpen(true)}>
              <Plus size={14} />
              Nouveau patient
            </Button>
          )}
        </CardHeader>
        <CardContent className="space-y-4">
          <div>
            <Label>Recherche</Label>
            <Input
              placeholder="Nom, téléphone ou identifiant..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>

          {patientsQuery.isError ? (
            <ErrorState message={apiErrorMessage(patientsQuery.error)} onRetry={() => patientsQuery.refetch()} />
          ) : (
            <DataTable
              columns={columns}
              data={filtered}
              rowKey={(row) => row.id}
              isLoading={patientsQuery.isLoading}
              onRowClick={(row) => navigate(`/patients/${row.id}`)}
              emptyState={
                <EmptyState
                  icon={Users}
                  title="Aucun patient trouvé"
                  description={
                    search.trim()
                      ? "Aucun patient ne correspond à cette recherche."
                      : "Aucun patient n'a encore été enregistré pour cette structure."
                  }
                  actionLabel={canCreate ? "Nouveau patient" : undefined}
                  onAction={canCreate ? () => setCreateOpen(true) : undefined}
                />
              }
            />
          )}
        </CardContent>
      </Card>

      <PatientQuickCreateDialog
        open={createOpen}
        onOpenChange={setCreateOpen}
        onPatientReady={(patient) => navigate(`/patients/${patient.id}`)}
      />
    </div>
  );
}

export function PatientsDirectoryRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("patients.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur la consultation des données patients."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <PatientsDirectoryPage />;
}
