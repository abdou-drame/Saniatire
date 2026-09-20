import { BedDouble, Building2, Pencil, Plus, Trash2 } from "lucide-react";
import { useEffect, useState, type FormEvent, type MouseEvent } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { ConfirmDialog } from "@/components/ui/confirm-dialog";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { FieldError, Label } from "@/components/ui/label";
import { Input } from "@/components/ui/input";
import { Select } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/loading-state";
import { useAuth } from "@/hooks/use-auth";
import { useDeleteSite, useSites } from "@/hooks/use-sites";
import { useStructure, useUpdateStructure } from "@/hooks/use-structure";
import { useWardsWithBeds } from "@/hooks/use-hospitalizations";
import { apiErrorMessage } from "@/lib/api-error";
import { SiteFormDialog } from "@/pages/structures/site-form-dialog";
import { WardBedsDialog } from "@/pages/structures/ward-beds-dialog";
import { WardFormDialog } from "@/pages/structures/ward-form-dialog";
import type { Site, Structure, StructureType, Ward } from "@/types/api";

const STRUCTURE_TYPE_LABEL: Record<StructureType, string> = {
  cabinet: "Cabinet",
  centre_specialise: "Centre spécialisé",
  laboratoire: "Laboratoire",
  imagerie: "Imagerie",
  clinique: "Clinique",
  polyclinique: "Polyclinique",
  groupe_sante: "Groupe de santé",
};

const STRUCTURE_TYPE_VALUES: StructureType[] = [
  "cabinet",
  "centre_specialise",
  "laboratoire",
  "imagerie",
  "clinique",
  "polyclinique",
  "groupe_sante",
];

function StructureForm({ structure, canEdit }: { structure: Structure; canEdit: boolean }) {
  const updateStructure = useUpdateStructure();

  const [code, setCode] = useState(structure.code);
  const [legalName, setLegalName] = useState(structure.legal_name);
  const [tradeName, setTradeName] = useState(structure.trade_name ?? "");
  const [type, setType] = useState<StructureType>(structure.type);
  const [address, setAddress] = useState(structure.address ?? "");
  const [city, setCity] = useState(structure.city ?? "");
  const [country, setCountry] = useState(structure.country ?? "");
  const [phone, setPhone] = useState(structure.phone ?? "");
  const [email, setEmail] = useState(structure.email ?? "");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitSuccess, setSubmitSuccess] = useState(false);

  useEffect(() => {
    setCode(structure.code);
    setLegalName(structure.legal_name);
    setTradeName(structure.trade_name ?? "");
    setType(structure.type);
    setAddress(structure.address ?? "");
    setCity(structure.city ?? "");
    setCountry(structure.country ?? "");
    setPhone(structure.phone ?? "");
    setEmail(structure.email ?? "");
  }, [structure]);

  function validate(): boolean {
    const errors: Record<string, string> = {};
    if (!code.trim()) errors.code = "Le code est obligatoire.";
    if (!legalName.trim()) errors.legalName = "La raison sociale est obligatoire.";
    setFieldErrors(errors);
    return Object.keys(errors).length === 0;
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault();
    setSubmitError(null);
    setSubmitSuccess(false);
    if (!validate()) return;

    try {
      await updateStructure.mutateAsync({
        code: code.trim(),
        legal_name: legalName.trim(),
        trade_name: tradeName.trim() || null,
        type,
        address: address.trim() || null,
        city: city.trim() || null,
        country: country.trim() || null,
        phone: phone.trim() || null,
        email: email.trim() || null,
        is_active: structure.is_active,
      });
      setSubmitSuccess(true);
    } catch (error) {
      setSubmitError(apiErrorMessage(error));
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      {submitError && (
        <p className="rounded-md border border-danger/30 bg-danger/5 px-3 py-2 text-sm text-danger">{submitError}</p>
      )}
      {submitSuccess && (
        <p className="rounded-md border border-success/30 bg-success/5 px-3 py-2 text-sm text-success">
          Structure mise à jour.
        </p>
      )}

      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label htmlFor="structure-code">Code</Label>
          <Input id="structure-code" value={code} onChange={(e) => setCode(e.target.value)} disabled={!canEdit} />
          <FieldError>{fieldErrors.code}</FieldError>
        </div>
        <div>
          <Label htmlFor="structure-type">Type</Label>
          <Select id="structure-type" value={type} onChange={(e) => setType(e.target.value as StructureType)} disabled={!canEdit}>
            {STRUCTURE_TYPE_VALUES.map((value) => (
              <option key={value} value={value}>
                {STRUCTURE_TYPE_LABEL[value]}
              </option>
            ))}
          </Select>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label htmlFor="structure-legal-name">Raison sociale</Label>
          <Input id="structure-legal-name" value={legalName} onChange={(e) => setLegalName(e.target.value)} disabled={!canEdit} />
          <FieldError>{fieldErrors.legalName}</FieldError>
        </div>
        <div>
          <Label htmlFor="structure-trade-name">Nom commercial</Label>
          <Input id="structure-trade-name" value={tradeName} onChange={(e) => setTradeName(e.target.value)} disabled={!canEdit} />
        </div>
      </div>

      <div>
        <Label htmlFor="structure-address">Adresse</Label>
        <Input id="structure-address" value={address} onChange={(e) => setAddress(e.target.value)} disabled={!canEdit} />
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label htmlFor="structure-city">Ville</Label>
          <Input id="structure-city" value={city} onChange={(e) => setCity(e.target.value)} disabled={!canEdit} />
        </div>
        <div>
          <Label htmlFor="structure-country">Pays</Label>
          <Input id="structure-country" value={country} onChange={(e) => setCountry(e.target.value)} disabled={!canEdit} />
        </div>
      </div>

      <div className="grid grid-cols-2 gap-3">
        <div>
          <Label htmlFor="structure-phone">Téléphone</Label>
          <Input id="structure-phone" value={phone} onChange={(e) => setPhone(e.target.value)} disabled={!canEdit} />
        </div>
        <div>
          <Label htmlFor="structure-email">Email</Label>
          <Input id="structure-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} disabled={!canEdit} />
        </div>
      </div>

      {canEdit && (
        <div className="flex justify-end">
          <Button type="submit" disabled={updateStructure.isPending}>
            {updateStructure.isPending ? "Enregistrement..." : "Enregistrer"}
          </Button>
        </div>
      )}
    </form>
  );
}

function SitesSection({ canManage }: { canManage: boolean }) {
  const sitesQuery = useSites();
  const deleteSite = useDeleteSite();
  const [formOpen, setFormOpen] = useState(false);
  const [editingSite, setEditingSite] = useState<Site | null>(null);
  const [siteToDelete, setSiteToDelete] = useState<Site | null>(null);
  const [deleteError, setDeleteError] = useState<string | null>(null);

  const columns: DataTableColumn<Site>[] = [
    { key: "name", header: "Nom", accessor: (row) => row.name },
    { key: "city", header: "Ville", accessor: (row) => row.city ?? "—" },
    { key: "phone", header: "Téléphone", accessor: (row) => row.phone ?? "—" },
    {
      key: "is_active",
      header: "Statut",
      render: (row) => <Badge status={row.is_active ? "success" : "neutral"}>{row.is_active ? "Actif" : "Inactif"}</Badge>,
    },
    ...(canManage
      ? [
          {
            key: "actions",
            header: "",
            align: "right" as const,
            render: (row: Site) => (
              <div className="flex justify-end gap-1">
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={(e: MouseEvent) => {
                    e.stopPropagation();
                    setEditingSite(row);
                    setFormOpen(true);
                  }}
                >
                  <Pencil size={14} />
                </Button>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={(e: MouseEvent) => {
                    e.stopPropagation();
                    setSiteToDelete(row);
                  }}
                >
                  <Trash2 size={14} />
                </Button>
              </div>
            ),
          },
        ]
      : []),
  ];

  async function handleDelete() {
    if (!siteToDelete) return;
    setDeleteError(null);
    try {
      await deleteSite.mutateAsync(siteToDelete.id);
      setSiteToDelete(null);
    } catch (error) {
      setDeleteError(apiErrorMessage(error));
    }
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle>Sites</CardTitle>
        {canManage && (
          <Button
            size="sm"
            onClick={() => {
              setEditingSite(null);
              setFormOpen(true);
            }}
          >
            <Plus size={14} />
            Nouveau site
          </Button>
        )}
      </CardHeader>
      <CardContent className="space-y-4">
        {sitesQuery.isError ? (
          <ErrorState message={apiErrorMessage(sitesQuery.error)} onRetry={() => sitesQuery.refetch()} />
        ) : (
          <DataTable
            columns={columns}
            data={sitesQuery.data ?? []}
            rowKey={(row) => row.id}
            isLoading={sitesQuery.isLoading}
            emptyState={<EmptyState icon={Building2} title="Aucun site" description="Créez le premier site de votre structure." />}
          />
        )}

        {deleteError && <p className="text-sm text-danger">{deleteError}</p>}
      </CardContent>

      <SiteFormDialog open={formOpen} onOpenChange={setFormOpen} site={editingSite} />

      <ConfirmDialog
        open={Boolean(siteToDelete)}
        onOpenChange={(open) => !open && setSiteToDelete(null)}
        title="Supprimer ce site ?"
        description={`Le site « ${siteToDelete?.name} » sera définitivement supprimé.`}
        confirmLabel="Supprimer"
        isPending={deleteSite.isPending}
        onConfirm={handleDelete}
      />
    </Card>
  );
}

function WardsSection({ canManage }: { canManage: boolean }) {
  const wardsQuery = useWardsWithBeds();
  const sitesQuery = useSites();
  const [formOpen, setFormOpen] = useState(false);
  const [editingWard, setEditingWard] = useState<Ward | null>(null);
  const [bedsWard, setBedsWard] = useState<Ward | null>(null);

  const siteName = (siteId: number) => sitesQuery.data?.find((s) => s.id === siteId)?.name ?? "—";

  const columns: DataTableColumn<Ward>[] = [
    { key: "name", header: "Service", accessor: (row) => row.name },
    { key: "site", header: "Site", accessor: (row) => siteName(row.site_id) },
    { key: "beds_count", header: "Lits", accessor: (row) => row.beds?.length ?? 0 },
    {
      key: "actions",
      header: "",
      align: "right" as const,
      render: (row: Ward) => (
        <div className="flex justify-end gap-1">
          <Button
            variant="ghost"
            size="sm"
            onClick={(e: MouseEvent) => {
              e.stopPropagation();
              setBedsWard(row);
            }}
          >
            <BedDouble size={14} />
            Lits
          </Button>
          {canManage && (
            <Button
              variant="ghost"
              size="sm"
              onClick={(e: MouseEvent) => {
                e.stopPropagation();
                setEditingWard(row);
                setFormOpen(true);
              }}
            >
              <Pencil size={14} />
            </Button>
          )}
        </div>
      ),
    },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Services et lits</CardTitle>
        {canManage && (
          <Button
            size="sm"
            onClick={() => {
              setEditingWard(null);
              setFormOpen(true);
            }}
          >
            <Plus size={14} />
            Nouveau service
          </Button>
        )}
      </CardHeader>
      <CardContent>
        {wardsQuery.isError ? (
          <ErrorState message={apiErrorMessage(wardsQuery.error)} onRetry={() => wardsQuery.refetch()} />
        ) : (
          <DataTable
            columns={columns}
            data={wardsQuery.data ?? []}
            rowKey={(row) => row.id}
            isLoading={wardsQuery.isLoading}
            emptyState={
              <EmptyState
                icon={BedDouble}
                title="Aucun service"
                description="Créez le premier service d'hospitalisation de votre structure."
              />
            }
          />
        )}
      </CardContent>

      <WardFormDialog open={formOpen} onOpenChange={setFormOpen} ward={editingWard} />
      <WardBedsDialog open={Boolean(bedsWard)} onOpenChange={(open) => !open && setBedsWard(null)} ward={bedsWard} />
    </Card>
  );
}

export function StructuresPage() {
  const { hasPermission } = useAuth();
  const structureQuery = useStructure();
  const canEditStructure = hasPermission("structures.update");
  const canManageSites = hasPermission("sites.create") || hasPermission("sites.update") || hasPermission("sites.delete");
  const canManageWards = hasPermission("hospitalisation.wards_manage");

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Structure</CardTitle>
        </CardHeader>
        <CardContent>
          {structureQuery.isError ? (
            <ErrorState message={apiErrorMessage(structureQuery.error)} onRetry={() => structureQuery.refetch()} />
          ) : structureQuery.isLoading || !structureQuery.data ? (
            <Skeleton className="h-64 w-full" />
          ) : (
            <StructureForm structure={structureQuery.data} canEdit={canEditStructure} />
          )}
        </CardContent>
      </Card>

      {hasPermission("sites.view") && <SitesSection canManage={canManageSites} />}
      {hasPermission("hospitalisation.view") && <WardsSection canManage={canManageWards} />}
    </div>
  );
}
