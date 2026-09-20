import { LoaderCircle, Pencil, Plus, ShieldCheck, Trash2 } from "lucide-react";
import { useEffect, useState } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { roleLabel } from "@/config/role-labels";
import { useAuth } from "@/hooks/use-auth";
import {
  useApprovalRules,
  useCreateApprovalRule,
  useDeleteApprovalRule,
  useUpdateApprovalRule,
} from "@/hooks/use-purchase-orders";
import { useUserRoles } from "@/hooks/use-user-accounts";
import { apiErrorMessage } from "@/lib/api-error";
import { formatFcfa } from "@/lib/format";
import type { ApprovalRule } from "@/types/api";

/**
 * Circuit d'approbation des commandes d'achat (§2 cahier des charges) : une
 * structure définit ses propres niveaux/seuils/rôles via ces écrans, exactement
 * le mécanisme déjà exercé côté backend à l'étape 5a — le hook useApprovalRules
 * préexistant sert de base plutôt que d'être recréé.
 */
export function ApprovalRulesSection() {
  const { hasPermission } = useAuth();
  const rulesQuery = useApprovalRules();
  const deleteRule = useDeleteApprovalRule();
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingRule, setEditingRule] = useState<ApprovalRule | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const canManage = hasPermission("achats.update");

  function openCreate() {
    setEditingRule(null);
    setDialogOpen(true);
  }

  function openEdit(rule: ApprovalRule) {
    setEditingRule(rule);
    setDialogOpen(true);
  }

  function handleDelete(rule: ApprovalRule) {
    setDeleteError(null);
    deleteRule.mutate(rule.id, {
      onError: (err) => setDeleteError(apiErrorMessage(err)),
    });
  }

  const columns: DataTableColumn<ApprovalRule>[] = [
    { key: "level", header: "Niveau", accessor: (row) => row.level, sortable: true },
    {
      key: "min_amount",
      header: "Seuil (montant minimum)",
      align: "right",
      render: (row) => formatFcfa(Number(row.min_amount)),
      accessor: (row) => Number(row.min_amount),
      sortable: true,
    },
    {
      key: "role_name",
      header: "Rôle requis",
      render: (row) => <Badge status="accent">{roleLabel(row.role_name)}</Badge>,
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            align: "right" as const,
            render: (row: ApprovalRule) => (
              <div className="flex justify-end gap-1">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={(e) => {
                    e.stopPropagation();
                    openEdit(row);
                  }}
                >
                  <Pencil size={14} />
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={(e) => {
                    e.stopPropagation();
                    handleDelete(row);
                  }}
                  disabled={deleteRule.isPending}
                >
                  <Trash2 size={14} />
                </Button>
              </div>
            ),
          },
        ]
      : []),
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Règles d'approbation</CardTitle>
        {canManage && (
          <Button size="sm" onClick={openCreate}>
            <Plus size={14} />
            Nouvelle règle
          </Button>
        )}
      </CardHeader>
      <CardContent className="space-y-3">
        {deleteError && (
          <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
            {deleteError}
          </p>
        )}
        {rulesQuery.isError ? (
          <ErrorState message={apiErrorMessage(rulesQuery.error)} onRetry={() => rulesQuery.refetch()} />
        ) : (
          <DataTable
            columns={columns}
            data={rulesQuery.data?.data ?? []}
            rowKey={(row) => row.id}
            isLoading={rulesQuery.isLoading}
            emptyState={
              <EmptyState
                icon={ShieldCheck}
                title="Aucune règle d'approbation"
                description="Aucun seuil de validation n'a encore été configuré."
                actionLabel={canManage ? "Nouvelle règle" : undefined}
                onAction={canManage ? openCreate : undefined}
              />
            }
          />
        )}
      </CardContent>

      <ApprovalRuleDialog open={dialogOpen} onOpenChange={setDialogOpen} rule={editingRule} />
    </Card>
  );
}

function ApprovalRuleDialog({
  open,
  onOpenChange,
  rule,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  rule: ApprovalRule | null;
}) {
  const [level, setLevel] = useState("");
  const [minAmount, setMinAmount] = useState("");
  const [roleName, setRoleName] = useState("");
  const [error, setError] = useState<string | null>(null);

  const rolesQuery = useUserRoles();
  const createRule = useCreateApprovalRule();
  const updateRule = useUpdateApprovalRule();
  const isEditing = Boolean(rule);
  const isPending = createRule.isPending || updateRule.isPending;

  useEffect(() => {
    if (open) {
      setLevel(rule ? String(rule.level) : "");
      setMinAmount(rule ? String(rule.min_amount) : "");
      setRoleName(rule?.role_name ?? "");
      setError(null);
    }
  }, [open, rule]);

  const canSubmit = level.trim().length > 0 && minAmount.trim().length > 0 && roleName.length > 0;

  function handleSubmit() {
    if (!canSubmit) return;
    setError(null);
    const input = { level: Number(level), min_amount: Number(minAmount), role_name: roleName };
    if (rule) {
      updateRule.mutate(
        { id: rule.id, ...input },
        {
          onSuccess: () => onOpenChange(false),
          onError: (err) => setError(apiErrorMessage(err)),
        },
      );
    } else {
      createRule.mutate(input, {
        onSuccess: () => onOpenChange(false),
        onError: (err) => setError(apiErrorMessage(err)),
      });
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{isEditing ? "Modifier la règle" : "Nouvelle règle d'approbation"}</DialogTitle>
          <DialogDescription>
            Un niveau de validation, le montant minimum à partir duquel il s'applique, et le rôle habilité à valider
            ce niveau.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Niveau</Label>
              <Input type="number" min={1} value={level} onChange={(e) => setLevel(e.target.value)} />
            </div>
            <div>
              <Label>Seuil (montant minimum)</Label>
              <Input type="number" min={0} step="0.01" value={minAmount} onChange={(e) => setMinAmount(e.target.value)} />
            </div>
          </div>

          <div>
            <Label>Rôle requis pour valider</Label>
            <Select value={roleName} onChange={(e) => setRoleName(e.target.value)} disabled={rolesQuery.isLoading}>
              <option value="">Sélectionner un rôle</option>
              {(rolesQuery.data ?? []).map((role) => (
                <option key={role} value={role}>
                  {roleLabel(role)}
                </option>
              ))}
            </Select>
          </div>

          {error && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>
          )}
        </div>

        <DialogFooter>
          <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={isPending}>
            Annuler
          </Button>
          <Button onClick={handleSubmit} disabled={!canSubmit || isPending}>
            {isPending && <LoaderCircle size={16} className="animate-spin" />}
            {isEditing ? "Enregistrer" : "Créer"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
