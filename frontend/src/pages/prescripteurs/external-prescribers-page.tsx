import { LoaderCircle, Lock, Plus, Send, Stethoscope } from "lucide-react";
import { useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { ExternalPrescriberFormDialog } from "@/components/prescripteurs/external-prescriber-form-dialog";
import { useAuth } from "@/hooks/use-auth";
import { useExternalPrescribers, useSendExternalPrescriberActivation } from "@/hooks/use-external-prescribers";
import { apiErrorMessage } from "@/lib/api-error";
import type { PrescriberUser } from "@/types/api";

/**
 * Écran de gestion des prescripteurs externes (personnel administratif),
 * distinct du portail self-service du prescripteur (pages/portal-prescripteur).
 * Le lien d'activation envoyé ici est le même mécanisme que celui du
 * patient (PortalActivationService) — le prescripteur définit lui-même son
 * mot de passe en suivant le lien, jamais saisi ici.
 */
export function ExternalPrescribersPage() {
  const { hasPermission } = useAuth();
  const canCreate = hasPermission("prescripteurs.create");
  const canUpdate = hasPermission("prescripteurs.update");

  const prescribersQuery = useExternalPrescribers();
  const sendActivation = useSendExternalPrescriberActivation();

  const [formOpen, setFormOpen] = useState(false);
  const [editingPrescriber, setEditingPrescriber] = useState<PrescriberUser | null>(null);
  const [activationError, setActivationError] = useState<string | null>(null);
  const [activationSuccessId, setActivationSuccessId] = useState<number | null>(null);

  function openCreateDialog() {
    setEditingPrescriber(null);
    setFormOpen(true);
  }

  function openEditDialog(prescriber: PrescriberUser) {
    if (!canUpdate) return;
    setEditingPrescriber(prescriber);
    setFormOpen(true);
  }

  function handleSendActivation(prescriber: PrescriberUser) {
    setActivationError(null);
    setActivationSuccessId(null);
    sendActivation.mutate(prescriber.id, {
      onSuccess: () => setActivationSuccessId(prescriber.id),
      onError: (err) => setActivationError(apiErrorMessage(err)),
    });
  }

  const columns: DataTableColumn<PrescriberUser>[] = [
    {
      key: "nom",
      header: "Nom",
      render: (row) => (
        <div>
          <p className="font-medium text-text">{row.nom}</p>
          <p className="text-xs text-text-muted">{row.email}</p>
        </div>
      ),
    },
    { key: "specialite", header: "Spécialité", render: (row) => row.specialite ?? "—" },
    { key: "telephone", header: "Téléphone", render: (row) => row.telephone ?? "—" },
    {
      key: "statut",
      header: "Statut",
      render: (row) => <Badge status={row.statut === "actif" ? "success" : "neutral"}>{row.statut === "actif" ? "Actif" : "Inactif"}</Badge>,
    },
    {
      key: "portal",
      header: "Portail",
      render: (row) => {
        if (row.portal_activated_at) {
          return <Badge status="success">Activé</Badge>;
        }
        if (!canUpdate) {
          return <Badge status="warning">Non activé</Badge>;
        }
        return (
          <Button
            size="sm"
            variant="secondary"
            onClick={(e) => {
              e.stopPropagation();
              handleSendActivation(row);
            }}
            disabled={sendActivation.isPending}
          >
            {sendActivation.isPending ? <LoaderCircle size={13} className="animate-spin" /> : <Send size={13} />}
            {activationSuccessId === row.id ? "Lien envoyé" : "Envoyer le lien d'activation"}
          </Button>
        );
      },
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between gap-4">
        <div>
          <h1 className="font-heading text-xl font-semibold text-text">Prescripteurs externes</h1>
          <p className="mt-1 text-sm text-text-muted">
            Professionnels de santé externes pouvant adresser des demandes d'analyses ou d'imagerie.
          </p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Prescripteurs</CardTitle>
          {canCreate && (
            <Button size="sm" onClick={openCreateDialog}>
              <Plus size={14} />
              Nouveau prescripteur
            </Button>
          )}
        </CardHeader>
        <CardContent className="space-y-4">
          {activationError && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
              {activationError}
            </p>
          )}

          {prescribersQuery.isError ? (
            <ErrorState message={apiErrorMessage(prescribersQuery.error)} onRetry={() => prescribersQuery.refetch()} />
          ) : (
            <DataTable
              columns={columns}
              data={prescribersQuery.data ?? []}
              rowKey={(row) => row.id}
              isLoading={prescribersQuery.isLoading}
              onRowClick={canUpdate ? openEditDialog : undefined}
              emptyState={
                <EmptyState
                  icon={Stethoscope}
                  title="Aucun prescripteur externe"
                  description="Aucun prescripteur externe n'a encore été enregistré pour cette structure."
                  actionLabel={canCreate ? "Nouveau prescripteur" : undefined}
                  onAction={canCreate ? openCreateDialog : undefined}
                />
              }
            />
          )}
        </CardContent>
      </Card>

      <ExternalPrescriberFormDialog open={formOpen} onOpenChange={setFormOpen} prescriber={editingPrescriber} />
    </div>
  );
}

export function ExternalPrescribersRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("prescripteurs.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur la gestion des prescripteurs externes."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <ExternalPrescribersPage />;
}
