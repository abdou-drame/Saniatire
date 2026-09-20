import { Inbox, Lock, Plus, Send, ShieldAlert } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { SendReferralDialog } from "@/components/referencement/send-referral-dialog";
import { useAuth } from "@/hooks/use-auth";
import { usePatientReferrals } from "@/hooks/use-patient-referrals";
import { usePatientsDirectory } from "@/hooks/use-patients-directory";
import { useStructuresDirectory } from "@/hooks/use-structures-directory";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import { ReferralDetail } from "@/pages/referencement/referral-detail";
import { REFERRAL_STATUT_BADGE, REFERRAL_STATUT_LABEL } from "@/pages/referencement/referencement-status";
import type { PatientReferral, StructureDirectoryEntry } from "@/types/api";

type StructuresDirectory = ReturnType<typeof useStructuresDirectory>;

function truncate(text: string, max = 60): string {
  return text.length > max ? `${text.slice(0, max)}…` : text;
}

/** Même convention `#id` que complaints-section.tsx : repli si l'annuaire n'est
 * pas chargé (utilisateur sans referrals.create, cf. commentaire de
 * use-structures-directory.ts) ou si l'entrée n'y figure pas. */
function structureLabel(directory: StructuresDirectory, userStructureId: number | undefined, structureId: number): string {
  if (structureId === userStructureId) return "Notre structure";
  const entry = (directory.data ?? []).find((s: StructureDirectoryEntry) => s.id === structureId);
  if (!entry) return `Structure #${structureId}`;
  return entry.legal_name || entry.trade_name || entry.code;
}

/**
 * Bandeau permanent, visuellement marqué (pas une simple puce) : le
 * référencement inter-structures est la seule exception volontaire à
 * l'isolement strict entre structures dans cette application
 * (ReferralVisibilityScope remplace TenantScope uniquement ici). Ce texte
 * doit rester visible en toutes circonstances sur cet écran, jamais réduit à
 * un détail — voir radiant-sniffing-spindle.md.
 */
function IsolationExceptionBanner() {
  return (
    <div className="flex items-start gap-3 rounded-lg border border-warning/30 bg-warning/5 px-4 py-3">
      <ShieldAlert size={20} className="mt-0.5 shrink-0 text-warning" />
      <p className="text-sm text-text">
        Partage de données patient entre deux structures — le seul point de l'application où cela est explicitement
        autorisé et journalisé, jamais un accès inter-structures normal.
      </p>
    </div>
  );
}

/**
 * Un unique appel usePatientReferrals() (pas de filtre serveur — voir son
 * commentaire), partitionné côté client par user.structure_id : envoyés =
 * origine, reçus = destination. ReferralVisibilityScope garantit déjà que
 * chaque ligne renvoyée appartient à l'une de ces deux catégories (origine OU
 * destination) — la partition est donc exhaustive, aucune branche "autre" ne
 * doit exister ici.
 */
export function ReferencementPage() {
  const { user, hasPermission } = useAuth();
  const [selectedReferralId, setSelectedReferralId] = useState<number | null>(null);
  const [createOpen, setCreateOpen] = useState(false);

  const referralsQuery = usePatientReferrals();
  const patientsDirectory = usePatientsDirectory();
  const structuresDirectory = useStructuresDirectory(hasPermission("referrals.create"));

  const canCreate = hasPermission("referrals.create");
  const structureId = user?.structure_id;

  const referrals = referralsQuery.data ?? [];
  const sent = referrals.filter((r) => r.structure_origine_id === structureId);
  const received = referrals.filter((r) => r.structure_destination_id === structureId);

  const sentColumns: DataTableColumn<PatientReferral>[] = [
    { key: "id", header: "ID", align: "right", accessor: (row) => row.id },
    {
      key: "patient",
      header: "Patient",
      render: (row) => {
        const patient = patientsDirectory.byId.get(row.patient_id);
        return patient ? `${patient.first_name} ${patient.last_name}` : `Patient #${row.patient_id}`;
      },
    },
    {
      key: "destination",
      header: "Structure destinataire",
      render: (row) => structureLabel(structuresDirectory, structureId, row.structure_destination_id),
    },
    {
      key: "statut",
      header: "Statut",
      render: (row) => <Badge status={REFERRAL_STATUT_BADGE[row.statut]}>{REFERRAL_STATUT_LABEL[row.statut]}</Badge>,
    },
    { key: "motif", header: "Motif", render: (row) => truncate(row.motif) },
    { key: "created_at", header: "Envoyé le", render: (row) => formatDate(row.created_at) },
  ];

  const receivedColumns: DataTableColumn<PatientReferral>[] = [
    { key: "id", header: "ID", align: "right", accessor: (row) => row.id },
    { key: "patient", header: "Patient", render: (row) => `Patient #${row.patient_id}` },
    {
      key: "origine",
      header: "Structure d'origine",
      render: (row) => structureLabel(structuresDirectory, structureId, row.structure_origine_id),
    },
    {
      key: "statut",
      header: "Statut",
      render: (row) => <Badge status={REFERRAL_STATUT_BADGE[row.statut]}>{REFERRAL_STATUT_LABEL[row.statut]}</Badge>,
    },
    { key: "motif", header: "Motif", render: (row) => truncate(row.motif) },
    { key: "created_at", header: "Reçu le", render: (row) => formatDate(row.created_at) },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="font-heading text-xl font-semibold text-text">Référencement inter-structures</h1>
          <p className="mt-1 text-sm text-text-muted">
            Référencements de patients envoyés à ou reçus d'autres structures.
          </p>
        </div>
        {canCreate && (
          <Button onClick={() => setCreateOpen(true)}>
            <Plus size={14} />
            Nouveau référencement
          </Button>
        )}
      </div>

      <IsolationExceptionBanner />

      {referralsQuery.isError ? (
        <ErrorState message={apiErrorMessage(referralsQuery.error)} onRetry={() => referralsQuery.refetch()} />
      ) : (
        <>
          <Card>
            <CardHeader>
              <CardTitle>Référencements envoyés</CardTitle>
            </CardHeader>
            <CardContent>
              <DataTable
                columns={sentColumns}
                data={sent}
                rowKey={(row) => row.id}
                isLoading={referralsQuery.isLoading}
                onRowClick={(row) => setSelectedReferralId(row.id)}
                emptyState={
                  <EmptyState
                    icon={Send}
                    title="Aucun référencement envoyé"
                    description="Vous n'avez référé aucun patient vers une autre structure."
                    actionLabel={canCreate ? "Nouveau référencement" : undefined}
                    onAction={canCreate ? () => setCreateOpen(true) : undefined}
                  />
                }
              />
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Référencements reçus</CardTitle>
            </CardHeader>
            <CardContent>
              <DataTable
                columns={receivedColumns}
                data={received}
                rowKey={(row) => row.id}
                isLoading={referralsQuery.isLoading}
                onRowClick={(row) => setSelectedReferralId(row.id)}
                emptyState={
                  <EmptyState
                    icon={Inbox}
                    title="Aucun référencement reçu"
                    description="Aucune autre structure ne vous a référé de patient."
                  />
                }
              />
            </CardContent>
          </Card>
        </>
      )}

      {selectedReferralId && (
        <ReferralDetail referralId={selectedReferralId} onClose={() => setSelectedReferralId(null)} />
      )}

      <SendReferralDialog open={createOpen} onOpenChange={setCreateOpen} />
    </div>
  );
}

/**
 * Gardée sur referrals.view (patron LaboratoireRoute) : contrairement à
 * Qualité/Réclamations, ici tout rôle disposant d'accept/refuse/update
 * dispose aussi de view dans le seeder (voir RolePermissionSeeder) — pas
 * besoin du patron "sans garde de route" de QualiteRoute.
 */
export function ReferencementRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("referrals.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur le module Référencement."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <ReferencementPage />;
}
