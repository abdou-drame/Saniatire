import { BedDouble, CalendarPlus, LogOut, Percent } from "lucide-react";
import { useState } from "react";
import { AdmitPatientDialog } from "@/components/hospitalisation/admit-patient-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { KpiCard } from "@/components/ui/kpi-card";
import { Label } from "@/components/ui/label";
import { KpiRowSkeleton } from "@/components/ui/loading-state";
import { Select } from "@/components/ui/select";
import { PatientPicker } from "@/components/clinical/patient-picker";
import { useAuth } from "@/hooks/use-auth";
import { useHospitalizations, useHospitalizationStats, type HospitalizationFilters } from "@/hooks/use-hospitalizations";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDateTime } from "@/lib/datetime";
import {
  HOSPITALIZATION_STATUS_BADGE,
  HOSPITALIZATION_STATUS_LABEL,
} from "@/pages/hospitalisation/hospitalisation-status";
import { WardBedGrid } from "@/pages/hospitalisation/ward-bed-grid";
import type { Bed, Hospitalization, HospitalizationStatus, Patient, Ward } from "@/types/api";

const STATUS_OPTIONS: HospitalizationStatus[] = ["en_cours", "sorti", "transfere"];

export function HospitalisationPage() {
  const { user, hasPermission } = useAuth();

  const [statusFilter, setStatusFilter] = useState<HospitalizationStatus | "">("");
  const [patientFilter, setPatientFilter] = useState<Patient | null>(null);
  const [admitDialogOpen, setAdmitDialogOpen] = useState(false);
  const [admitPrefill, setAdmitPrefill] = useState<{ wardId: number; bedId: number } | null>(null);

  const statsQuery = useHospitalizationStats();

  const filters: HospitalizationFilters = {
    status: statusFilter || undefined,
    patientId: patientFilter?.id,
  };
  const hospitalizationsQuery = useHospitalizations(filters);

  const canAdmit = hasPermission("hospitalisation.create");

  function handleSelectFreeBed(ward: Ward, bed: Bed) {
    setAdmitPrefill({ wardId: ward.id, bedId: bed.id });
    setAdmitDialogOpen(true);
  }

  const columns: DataTableColumn<Hospitalization>[] = [
    {
      key: "patient",
      header: "Patient",
      render: (row) =>
        row.patient ? `${row.patient.first_name} ${row.patient.last_name} (${row.patient.patient_number})` : "—",
    },
    {
      key: "ward",
      header: "Service",
      render: (row) => row.ward?.name ?? "—",
    },
    {
      key: "bed",
      header: "Lit",
      render: (row) => (row.bed ? `${row.bed.room_number} — ${row.bed.bed_label}` : "—"),
    },
    {
      key: "attending_physician",
      header: "Médecin responsable",
      render: (row) => row.attending_physician_label ?? "—",
    },
    {
      key: "status",
      header: "Statut",
      render: (row) => (
        <Badge status={HOSPITALIZATION_STATUS_BADGE[row.status]}>{HOSPITALIZATION_STATUS_LABEL[row.status]}</Badge>
      ),
    },
    {
      key: "admitted_at",
      header: "Admission",
      render: (row) => formatDateTime(row.admitted_at),
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="font-heading text-xl font-semibold text-text">Hospitalisation</h1>
          <p className="mt-1 text-sm text-text-muted">Suivi des lits, admissions et sorties.</p>
        </div>
        {canAdmit && (
          <Button onClick={() => setAdmitDialogOpen(true)}>
            <CalendarPlus size={14} />
            Admettre un patient
          </Button>
        )}
      </div>

      {statsQuery.isLoading && <KpiRowSkeleton />}
      {statsQuery.isError && (
        <ErrorState message={apiErrorMessage(statsQuery.error)} onRetry={() => statsQuery.refetch()} />
      )}
      {statsQuery.data && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <KpiCard
            label="Lits occupés"
            value={`${statsQuery.data.lits_occupes} / ${statsQuery.data.lits_total}`}
            icon={BedDouble}
          />
          <KpiCard label="Taux d'occupation" value={statsQuery.data.taux_occupation} unit="%" icon={Percent} />
          <KpiCard label="Admissions du jour" value={statsQuery.data.admissions_du_jour} icon={CalendarPlus} />
          <KpiCard label="Sorties du jour" value={statsQuery.data.sorties_du_jour} icon={LogOut} />
        </div>
      )}

      <WardBedGrid onSelectFreeBed={canAdmit ? handleSelectFreeBed : undefined} />

      <Card>
        <CardHeader>
          <CardTitle>Hospitalisations</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Statut</Label>
              <Select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value as HospitalizationStatus | "")}
              >
                <option value="">Tous</option>
                {STATUS_OPTIONS.map((status) => (
                  <option key={status} value={status}>
                    {HOSPITALIZATION_STATUS_LABEL[status]}
                  </option>
                ))}
              </Select>
            </div>
            <div>
              <Label>Patient</Label>
              <PatientPicker value={patientFilter} onChange={setPatientFilter} placeholder="Rechercher un patient..." />
            </div>
          </div>

          {hospitalizationsQuery.isError ? (
            <ErrorState
              message={apiErrorMessage(hospitalizationsQuery.error)}
              onRetry={() => hospitalizationsQuery.refetch()}
            />
          ) : (
            <DataTable
              columns={columns}
              data={hospitalizationsQuery.data?.data ?? []}
              rowKey={(row) => row.id}
              isLoading={hospitalizationsQuery.isLoading}
              emptyState={
                <EmptyState
                  icon={BedDouble}
                  title="Aucune hospitalisation"
                  description="Aucune hospitalisation ne correspond à ces filtres."
                />
              }
            />
          )}
        </CardContent>
      </Card>

      {canAdmit && user && (
        <AdmitPatientDialog
          open={admitDialogOpen}
          onOpenChange={(next) => {
            setAdmitDialogOpen(next);
            if (!next) setAdmitPrefill(null);
          }}
          attendingPhysicianId={user.id}
          initialWardId={admitPrefill?.wardId}
          initialBedId={admitPrefill?.bedId}
          onAdmitted={() => {
            statsQuery.refetch();
          }}
        />
      )}
    </div>
  );
}
