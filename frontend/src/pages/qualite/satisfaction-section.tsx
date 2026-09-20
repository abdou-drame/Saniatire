import { Star } from "lucide-react";
import { useMemo, useState } from "react";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/input";
import { usePatientsDirectory } from "@/hooks/use-patients-directory";
import { useSatisfactionSurveys } from "@/hooks/use-satisfaction-surveys";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import type { PatientSatisfactionSurvey } from "@/types/api";

/**
 * Écran Qualité, section 2 "Satisfaction" — consultation/filtrage
 * uniquement, aucune création d'évaluation depuis cet écran (hors
 * périmètre confirmé par le plan).
 *
 * GET /patient-satisfaction-surveys ne filtre côté serveur que par
 * `service`/`patient_id` (voir use-satisfaction-surveys.ts) : aucun
 * paramètre de période n'existe côté backend
 * (PatientSatisfactionSurveyController::index). Le champ "Service" reste
 * donc un texte libre — aucun enum de service n'existe côté backend, on
 * n'en invente pas ici.
 */
export function SatisfactionSection() {
  const [service, setService] = useState("");
  const [dateFrom, setDateFrom] = useState("");
  const [dateTo, setDateTo] = useState("");

  const patientsDirectory = usePatientsDirectory();
  const surveysQuery = useSatisfactionSurveys({ service: service.trim() || undefined });

  // Filtre d'affichage uniquement sur les lignes déjà renvoyées par le
  // backend — jamais utilisé pour calculer une moyenne (le score moyen
  // affiché ailleurs vient exclusivement de score_moyen_satisfaction).
  const filteredSurveys = useMemo(() => {
    const rows = surveysQuery.data ?? [];
    return rows.filter((row) => {
      if (dateFrom && row.date < dateFrom) return false;
      if (dateTo && row.date > dateTo) return false;
      return true;
    });
  }, [surveysQuery.data, dateFrom, dateTo]);

  function patientLabel(patientId: number): string {
    const patient = patientsDirectory.byId.get(patientId);
    return patient ? `${patient.first_name} ${patient.last_name}` : `Patient #${patientId}`;
  }

  const columns: DataTableColumn<PatientSatisfactionSurvey>[] = [
    {
      key: "date",
      header: "Date",
      sortable: true,
      accessor: (row) => row.date,
      render: (row) => formatDate(row.date),
    },
    {
      key: "note",
      header: "Note",
      align: "right",
      sortable: true,
      accessor: (row) => row.note,
      render: (row) => `${row.note}/10`,
    },
    { key: "commentaire", header: "Commentaire", render: (row) => row.commentaire ?? "—" },
    { key: "service", header: "Service", render: (row) => row.service ?? "—" },
    {
      key: "prestation",
      header: "Prestation",
      render: (row) => {
        if (!row.prestation_type && row.prestation_id === null) return "—";
        if (row.prestation_type && row.prestation_id !== null) return `${row.prestation_type} #${row.prestation_id}`;
        return row.prestation_type ?? `#${row.prestation_id}`;
      },
    },
    { key: "patient", header: "Patient", render: (row) => patientLabel(row.patient_id) },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Satisfaction</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <Input
            placeholder="Service"
            value={service}
            onChange={(e) => setService(e.target.value)}
            aria-label="Filtrer par service"
          />
          <Input
            type="date"
            value={dateFrom}
            onChange={(e) => setDateFrom(e.target.value)}
            aria-label="Date de début"
          />
          <Input
            type="date"
            value={dateTo}
            onChange={(e) => setDateTo(e.target.value)}
            aria-label="Date de fin"
          />
        </div>

        {surveysQuery.isError ? (
          <ErrorState message={apiErrorMessage(surveysQuery.error)} onRetry={() => surveysQuery.refetch()} />
        ) : (
          <DataTable
            columns={columns}
            data={filteredSurveys}
            rowKey={(row) => row.id}
            isLoading={surveysQuery.isLoading}
            emptyState={
              <EmptyState
                icon={Star}
                title="Aucune évaluation de satisfaction"
                description="Aucune évaluation ne correspond aux filtres sélectionnés."
              />
            }
          />
        )}
      </CardContent>
    </Card>
  );
}
