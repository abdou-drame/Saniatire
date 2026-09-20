import { Lock, Plus, Video } from "lucide-react";
import { useState } from "react";
import { ScheduleTeleconsultationDialog } from "@/components/teleconsultation/schedule-teleconsultation-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { useAuth } from "@/hooks/use-auth";
import { usePatientsDirectory } from "@/hooks/use-patients-directory";
import { useTeleconsultations } from "@/hooks/use-teleconsultations";
import { useUsersDirectory } from "@/hooks/use-users-directory";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import {
  TELECONSULTATION_STATUT_BADGE,
  TELECONSULTATION_STATUT_LABEL,
} from "@/pages/teleconsultation/teleconsultation-status";
import { TeleconsultationDetail } from "@/pages/teleconsultation/teleconsultation-detail";
import type { Teleconsultation } from "@/types/api";

type PatientsDirectory = ReturnType<typeof usePatientsDirectory>;
type UsersDirectory = ReturnType<typeof useUsersDirectory>;

function patientLabel(directory: PatientsDirectory, patientId: number): string {
  const patient = directory.byId.get(patientId);
  return patient ? `${patient.first_name} ${patient.last_name}` : `Patient #${patientId}`;
}

function practitionerLabel(directory: UsersDirectory, practitionerId: number): string {
  const user = directory.byId.get(practitionerId);
  return user ? `${user.first_name} ${user.last_name}` : `Praticien #${practitionerId}`;
}

/**
 * Écran Téléconsultation — table dédiée (`teleconsultations`), jamais un
 * badge fabriqué sur le calendrier des rendez-vous existant : aucune colonne
 * type/is_teleconsultation n'existe sur Appointment (confirmé en lisant la
 * migration teleconsultations). GET /teleconsultations n'a pas de filtre
 * statut côté serveur, donc pas de filtre par statut ici — la liste
 * complète, paginée côté client par DataTable, suffit.
 */
export function TeleconsultationPage() {
  const { hasPermission } = useAuth();
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const [scheduleOpen, setScheduleOpen] = useState(false);

  const teleconsultationsQuery = useTeleconsultations();
  const patientsDirectory = usePatientsDirectory();
  const usersDirectory = useUsersDirectory(hasPermission("users.view"));

  const canCreate = hasPermission("teleconsultation.create");

  const columns: DataTableColumn<Teleconsultation>[] = [
    { key: "id", header: "ID", align: "right", accessor: (row) => row.id },
    { key: "patient", header: "Patient", render: (row) => patientLabel(patientsDirectory, row.patient_id) },
    {
      key: "praticien",
      header: "Praticien",
      render: (row) => practitionerLabel(usersDirectory, row.practitioner_id),
    },
    {
      key: "statut",
      header: "Statut",
      render: (row) => (
        <Badge status={TELECONSULTATION_STATUT_BADGE[row.statut]}>{TELECONSULTATION_STATUT_LABEL[row.statut]}</Badge>
      ),
    },
    { key: "created_at", header: "Planifiée le", render: (row) => formatDateTime(row.created_at) },
    {
      key: "started_at",
      header: "Démarrée le",
      render: (row) => (row.started_at ? formatDateTime(row.started_at) : "—"),
    },
    {
      key: "ended_at",
      header: "Clôturée le",
      render: (row) => (row.ended_at ? formatDateTime(row.ended_at) : "—"),
    },
  ];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-heading text-xl font-semibold text-text">Téléconsultation</h1>
        <p className="mt-1 text-sm text-text-muted">
          Consultations à distance planifiées à partir d'un rendez-vous existant.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Téléconsultations</CardTitle>
          {canCreate && (
            <Button size="sm" onClick={() => setScheduleOpen(true)}>
              <Plus size={14} />
              Nouvelle téléconsultation
            </Button>
          )}
        </CardHeader>
        <CardContent className="space-y-4">
          {teleconsultationsQuery.isError ? (
            <ErrorState
              message={apiErrorMessage(teleconsultationsQuery.error)}
              onRetry={() => teleconsultationsQuery.refetch()}
            />
          ) : (
            <DataTable
              columns={columns}
              data={teleconsultationsQuery.data ?? []}
              rowKey={(row) => row.id}
              isLoading={teleconsultationsQuery.isLoading}
              onRowClick={(row) => setSelectedId(row.id)}
              emptyState={
                <EmptyState
                  icon={Video}
                  title="Aucune téléconsultation"
                  description="Aucune téléconsultation n'a encore été planifiée."
                  actionLabel={canCreate ? "Nouvelle téléconsultation" : undefined}
                  onAction={canCreate ? () => setScheduleOpen(true) : undefined}
                />
              }
            />
          )}

          {selectedId && (
            <TeleconsultationDetail
              teleconsultationId={selectedId}
              onClose={() => setSelectedId(null)}
              patientsDirectory={patientsDirectory}
              usersDirectory={usersDirectory}
            />
          )}
        </CardContent>
      </Card>

      <ScheduleTeleconsultationDialog open={scheduleOpen} onOpenChange={setScheduleOpen} />
    </div>
  );
}

/**
 * Garde de route sur teleconsultation.view (patron LaboratoireRoute dans
 * App.tsx) — contrairement à Qualité/Réclamations (QualiteRoute, sans
 * garde), le RolePermissionSeeder attribue toujours teleconsultation.view
 * aux rôles qui ont .update/.cancel (médecin, secrétaire, direction), donc
 * aucun rôle n'a besoin d'agir sur une téléconsultation sans pouvoir aussi
 * voir l'écran — un verrou de route est donc sûr ici.
 */
export function TeleconsultationRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("teleconsultation.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur le module Téléconsultation."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <TeleconsultationPage />;
}
