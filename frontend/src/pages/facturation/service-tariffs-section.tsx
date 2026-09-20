import { LoaderCircle, Pencil, Plus, Tags, Trash2 } from "lucide-react";
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
import { useAuth } from "@/hooks/use-auth";
import {
  useCreateServiceTariff,
  useDeleteServiceTariff,
  useServiceTariffs,
  useUpdateServiceTariff,
} from "@/hooks/use-service-tariffs";
import { apiErrorMessage } from "@/lib/api-error";
import { formatFcfa } from "@/lib/format";
import type { ServiceTariff } from "@/types/api";

/**
 * Types de prestation facturables réellement gérés côté backend (Billable::
 * billingTariffCode(), un code par implémenteur — Consultation, LabOrder,
 * ImagingOrder par exam_type, SurgicalProcedure, KineSession, Hospitalization,
 * DialysisSession). BillingService::recordService() ne trouve un tarif que
 * par égalité stricte de code : le sélecteur ci-dessous n'offre donc que ces
 * codes exacts, jamais un code libre qui ne matchera jamais rien en facturation.
 */
const PRESTATION_TYPES: { code: string; categorie: string; libelle: string }[] = [
  { code: "CONSULTATION_GENERALE", categorie: "consultation", libelle: "Consultation générale" },
  { code: "LABORATOIRE_ANALYSE", categorie: "laboratoire", libelle: "Analyse de laboratoire" },
  { code: "IMAGERIE_RADIO", categorie: "imagerie", libelle: "Radiographie" },
  { code: "IMAGERIE_ECHO", categorie: "imagerie", libelle: "Échographie" },
  { code: "IMAGERIE_SCANNER", categorie: "imagerie", libelle: "Scanner" },
  { code: "IMAGERIE_IRM", categorie: "imagerie", libelle: "IRM" },
  { code: "CHIRURGIE_INTERVENTION", categorie: "chirurgie", libelle: "Intervention chirurgicale" },
  { code: "KINESITHERAPIE_SEANCE", categorie: "kinesitherapie", libelle: "Séance de kinésithérapie" },
  { code: "HOSPITALISATION_NUIT", categorie: "hospitalisation", libelle: "Nuitée d'hospitalisation" },
  { code: "DIALYSE_SEANCE", categorie: "dialyse", libelle: "Séance de dialyse" },
];

export function ServiceTariffsSection() {
  const { hasPermission } = useAuth();
  const tariffsQuery = useServiceTariffs();
  const deleteTariff = useDeleteServiceTariff();
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingTariff, setEditingTariff] = useState<ServiceTariff | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const canCreate = hasPermission("facturation.create");
  const canUpdate = hasPermission("facturation.update");
  const canDelete = hasPermission("facturation.delete");

  function openCreate() {
    setEditingTariff(null);
    setDialogOpen(true);
  }

  function openEdit(tariff: ServiceTariff) {
    setEditingTariff(tariff);
    setDialogOpen(true);
  }

  function handleDelete(tariff: ServiceTariff) {
    setDeleteError(null);
    deleteTariff.mutate(tariff.id, {
      onError: (err) => setDeleteError(apiErrorMessage(err)),
    });
  }

  const columns: DataTableColumn<ServiceTariff>[] = [
    { key: "code", header: "Code", accessor: (row) => row.code, sortable: true },
    { key: "libelle", header: "Prestation", accessor: (row) => row.libelle, sortable: true },
    { key: "categorie", header: "Catégorie", accessor: (row) => row.categorie },
    {
      key: "prix_unitaire",
      header: "Montant",
      align: "right",
      render: (row) => formatFcfa(Number(row.prix_unitaire)),
      accessor: (row) => Number(row.prix_unitaire),
      sortable: true,
    },
    {
      key: "actif",
      header: "Statut",
      render: (row) => <Badge status={row.actif ? "success" : "neutral"}>{row.actif ? "Actif" : "Inactif"}</Badge>,
    },
    ...((canUpdate || canDelete)
      ? [
          {
            key: "actions",
            header: "",
            align: "right" as const,
            render: (row: ServiceTariff) => (
              <div className="flex justify-end gap-1">
                {canUpdate && (
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
                )}
                {canDelete && (
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={(e) => {
                      e.stopPropagation();
                      handleDelete(row);
                    }}
                    disabled={deleteTariff.isPending}
                  >
                    <Trash2 size={14} />
                  </Button>
                )}
              </div>
            ),
          },
        ]
      : []),
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Grille tarifaire</CardTitle>
        {canCreate && (
          <Button size="sm" onClick={openCreate}>
            <Plus size={14} />
            Nouveau tarif
          </Button>
        )}
      </CardHeader>
      <CardContent className="space-y-3">
        {deleteError && (
          <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
            {deleteError}
          </p>
        )}
        {tariffsQuery.isError ? (
          <ErrorState message={apiErrorMessage(tariffsQuery.error)} onRetry={() => tariffsQuery.refetch()} />
        ) : (
          <DataTable
            columns={columns}
            data={tariffsQuery.data?.data ?? []}
            rowKey={(row) => row.id}
            isLoading={tariffsQuery.isLoading}
            emptyState={
              <EmptyState
                icon={Tags}
                title="Aucun tarif configuré"
                description="Aucune prestation n'a encore de tarif configuré."
                actionLabel={canCreate ? "Nouveau tarif" : undefined}
                onAction={canCreate ? openCreate : undefined}
              />
            }
          />
        )}
      </CardContent>

      <ServiceTariffDialog open={dialogOpen} onOpenChange={setDialogOpen} tariff={editingTariff} />
    </Card>
  );
}

function ServiceTariffDialog({
  open,
  onOpenChange,
  tariff,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  tariff: ServiceTariff | null;
}) {
  const [code, setCode] = useState("");
  const [libelle, setLibelle] = useState("");
  const [prixUnitaire, setPrixUnitaire] = useState("");
  const [actif, setActif] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const createTariff = useCreateServiceTariff();
  const updateTariff = useUpdateServiceTariff();
  const isEditing = Boolean(tariff);
  const isPending = createTariff.isPending || updateTariff.isPending;
  const selectedType = PRESTATION_TYPES.find((t) => t.code === code);

  useEffect(() => {
    if (open) {
      setCode(tariff?.code ?? "");
      setLibelle(tariff?.libelle ?? "");
      setPrixUnitaire(tariff ? String(tariff.prix_unitaire) : "");
      setActif(tariff?.actif ?? true);
      setError(null);
    }
  }, [open, tariff]);

  const canSubmit = code.trim().length > 0 && libelle.trim().length > 0 && prixUnitaire.trim().length > 0;

  function handleTypeChange(newCode: string) {
    setCode(newCode);
    const type = PRESTATION_TYPES.find((t) => t.code === newCode);
    if (type) setLibelle(type.libelle);
  }

  function handleSubmit() {
    if (!canSubmit) return;
    setError(null);
    if (tariff) {
      updateTariff.mutate(
        { id: tariff.id, libelle: libelle.trim(), prix_unitaire: Number(prixUnitaire), actif },
        {
          onSuccess: () => onOpenChange(false),
          onError: (err) => setError(apiErrorMessage(err)),
        },
      );
    } else {
      const type = PRESTATION_TYPES.find((t) => t.code === code);
      if (!type) return;
      createTariff.mutate(
        { code: type.code, categorie: type.categorie, libelle: libelle.trim(), prix_unitaire: Number(prixUnitaire) },
        {
          onSuccess: () => onOpenChange(false),
          onError: (err) => setError(apiErrorMessage(err)),
        },
      );
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{isEditing ? "Modifier le tarif" : "Nouveau tarif"}</DialogTitle>
          <DialogDescription>
            Le montant configuré ici sera automatiquement repris lors de la facturation de cette prestation.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div>
            <Label>Type de prestation</Label>
            {isEditing ? (
              <p className="text-sm text-text">
                {selectedType?.libelle ?? tariff?.libelle} <span className="text-text-subtle">({code})</span>
              </p>
            ) : (
              <Select value={code} onChange={(e) => handleTypeChange(e.target.value)}>
                <option value="">Sélectionner un type de prestation</option>
                {PRESTATION_TYPES.map((type) => (
                  <option key={type.code} value={type.code}>
                    {type.libelle} ({type.code})
                  </option>
                ))}
              </Select>
            )}
          </div>

          <div>
            <Label>Libellé</Label>
            <Input value={libelle} onChange={(e) => setLibelle(e.target.value)} placeholder="ex. Consultation générale" />
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Montant</Label>
              <Input
                type="number"
                min={0}
                step="0.01"
                value={prixUnitaire}
                onChange={(e) => setPrixUnitaire(e.target.value)}
              />
            </div>
            {isEditing && (
              <div>
                <Label>Statut</Label>
                <Select value={actif ? "1" : "0"} onChange={(e) => setActif(e.target.value === "1")}>
                  <option value="1">Actif</option>
                  <option value="0">Inactif</option>
                </Select>
              </div>
            )}
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
