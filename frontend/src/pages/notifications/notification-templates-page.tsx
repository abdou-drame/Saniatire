import { Bell, Lock, Plus, Trash2 } from "lucide-react";
import { useState } from "react";
import { NotificationTemplateFormDialog } from "@/components/notifications/notification-template-form-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { useAuth } from "@/hooks/use-auth";
import { useDeleteNotificationTemplate, useNotificationTemplates } from "@/hooks/use-notification-templates";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import {
  NOTIFICATION_CANAL_BADGE,
  NOTIFICATION_CANAL_LABEL,
  NOTIFICATION_CANAL_STATUS_LABEL,
} from "@/pages/notifications/notification-channel-status";
import type { NotificationCanal, NotificationTemplate } from "@/types/api";

const CANAL_OPTIONS: NotificationCanal[] = ["email", "sms", "whatsapp", "push"];

/**
 * Seul le rôle administrateur détient notifications.create/.update/.delete
 * (cf. RolePermissionSeeder) — direction n'a que .view/.export. Un utilisateur
 * direction voit donc la liste en lecture seule : ni bouton "Nouveau
 * template", ni clic de ligne, ni action de suppression. C'est le
 * comportement normal, pas une lacune de cet écran.
 */
export function NotificationTemplatesPage() {
  const { hasPermission } = useAuth();
  const [typeEvenementFilter, setTypeEvenementFilter] = useState("");
  const [canalFilter, setCanalFilter] = useState<NotificationCanal | "">("");
  const [formOpen, setFormOpen] = useState(false);
  const [editingTemplate, setEditingTemplate] = useState<NotificationTemplate | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<NotificationTemplate | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const canCreate = hasPermission("notifications.create");
  const canUpdate = hasPermission("notifications.update");
  const canDelete = hasPermission("notifications.delete");

  const templatesQuery = useNotificationTemplates({
    type_evenement: typeEvenementFilter.trim() || undefined,
    canal: canalFilter || undefined,
  });
  const deleteTemplate = useDeleteNotificationTemplate();

  function openCreate() {
    setEditingTemplate(null);
    setFormOpen(true);
  }

  function openEdit(template: NotificationTemplate) {
    setEditingTemplate(template);
    setFormOpen(true);
  }

  const columns: DataTableColumn<NotificationTemplate>[] = [
    { key: "type_evenement", header: "Type d'événement", accessor: (row) => row.type_evenement },
    {
      key: "canal",
      header: "Canal",
      render: (row) => (
        <Badge status={NOTIFICATION_CANAL_BADGE[row.canal]}>
          {NOTIFICATION_CANAL_LABEL[row.canal]} · {NOTIFICATION_CANAL_STATUS_LABEL[row.canal]}
        </Badge>
      ),
    },
    { key: "sujet", header: "Sujet", render: (row) => row.sujet ?? "—" },
    {
      key: "actif",
      header: "Statut",
      render: (row) => <Badge status={row.actif ? "success" : "neutral"}>{row.actif ? "Actif" : "Inactif"}</Badge>,
    },
    { key: "updated_at", header: "Modifié le", render: (row) => formatDate(row.updated_at) },
    {
      key: "actions",
      header: "",
      align: "right",
      render: (row) =>
        canDelete ? (
          <Button
            variant="ghost"
            size="icon"
            onClick={(e) => {
              e.stopPropagation();
              setDeleteError(null);
              setDeleteTarget(row);
            }}
            aria-label="Supprimer le template"
          >
            <Trash2 size={14} />
          </Button>
        ) : null,
    },
  ];

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h1 className="font-heading text-xl font-semibold text-text">Templates de notification</h1>
          <p className="mt-1 text-sm text-text-muted">
            Sujet et contenu des notifications envoyées automatiquement pour chaque type d'événement, par canal.
            Seul le canal e-mail envoie réellement un message aujourd'hui — SMS, WhatsApp et Push sont simulés
            (journalisés uniquement, aucun fournisseur n'est branché dans cette installation).
          </p>
        </div>
        {canCreate && (
          <Button onClick={openCreate}>
            <Plus size={14} />
            Nouveau template
          </Button>
        )}
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Filtres</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Type d'événement</Label>
              <Input
                value={typeEvenementFilter}
                onChange={(e) => setTypeEvenementFilter(e.target.value)}
                placeholder="ex. rdv_rappel"
              />
            </div>
            <div>
              <Label>Canal</Label>
              <Select value={canalFilter} onChange={(e) => setCanalFilter(e.target.value as NotificationCanal | "")}>
                <option value="">Tous</option>
                {CANAL_OPTIONS.map((canal) => (
                  <option key={canal} value={canal}>
                    {NOTIFICATION_CANAL_LABEL[canal]}
                  </option>
                ))}
              </Select>
            </div>
          </div>

          {templatesQuery.isError ? (
            <ErrorState message={apiErrorMessage(templatesQuery.error)} onRetry={() => templatesQuery.refetch()} />
          ) : (
            <DataTable
              columns={columns}
              data={templatesQuery.data?.data ?? []}
              rowKey={(row) => row.id}
              isLoading={templatesQuery.isLoading}
              onRowClick={canUpdate ? openEdit : undefined}
              emptyState={
                <EmptyState
                  icon={Bell}
                  title="Aucun template"
                  description="Aucun template de notification ne correspond à ces filtres."
                  actionLabel={canCreate ? "Nouveau template" : undefined}
                  onAction={canCreate ? openCreate : undefined}
                />
              }
            />
          )}
        </CardContent>
      </Card>

      <NotificationTemplateFormDialog open={formOpen} onOpenChange={setFormOpen} template={editingTemplate} />

      <ConfirmDialog
        open={Boolean(deleteTarget)}
        onOpenChange={(open) => {
          if (!open) setDeleteTarget(null);
        }}
        title="Supprimer le template"
        description={
          deleteTarget
            ? `Supprimer définitivement le template « ${deleteTarget.type_evenement} » (${NOTIFICATION_CANAL_LABEL[deleteTarget.canal]}) ? Cette action est irréversible.`
            : ""
        }
        confirmLabel="Supprimer"
        isPending={deleteTemplate.isPending}
        onConfirm={() => {
          if (!deleteTarget) return;
          deleteTemplate.mutate(deleteTarget.id, {
            onSuccess: () => setDeleteTarget(null),
            onError: (err) => setDeleteError(apiErrorMessage(err)),
          });
        }}
      />
      {deleteError && (
        <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">{deleteError}</p>
      )}
    </div>
  );
}

/** Gardée sur notifications.view (patron ReferencementRoute / LaboratoireRoute). */
export function NotificationTemplatesRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("notifications.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur le module Notifications."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <NotificationTemplatesPage />;
}
