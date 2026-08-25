import { Building2, LoaderCircle, Plus } from "lucide-react";
import { useEffect, useState } from "react";
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
import { Label } from "@/components/ui/label";
import { useAuth } from "@/hooks/use-auth";
import { useCreateSupplier, useSuppliers } from "@/hooks/use-suppliers";
import { apiErrorMessage } from "@/lib/api-error";
import type { Supplier } from "@/types/api";

export function FournisseursSection() {
  const { hasPermission } = useAuth();
  const suppliersQuery = useSuppliers();
  const [dialogOpen, setDialogOpen] = useState(false);

  const canCreate = hasPermission("achats.create");

  const columns: DataTableColumn<Supplier>[] = [
    { key: "nom", header: "Nom", accessor: (row) => row.nom, sortable: true },
    { key: "contact", header: "Contact", render: (row) => row.contact ?? "—" },
    {
      key: "conditions_commerciales",
      header: "Conditions commerciales",
      render: (row) => row.conditions_commerciales ?? "—",
    },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Fournisseurs</CardTitle>
        {canCreate && (
          <Button size="sm" onClick={() => setDialogOpen(true)}>
            <Plus size={14} />
            Nouveau fournisseur
          </Button>
        )}
      </CardHeader>
      <CardContent>
        {suppliersQuery.isError ? (
          <ErrorState message={apiErrorMessage(suppliersQuery.error)} onRetry={() => suppliersQuery.refetch()} />
        ) : (
          <DataTable
            columns={columns}
            data={suppliersQuery.data?.data ?? []}
            rowKey={(row) => row.id}
            isLoading={suppliersQuery.isLoading}
            emptyState={
              <EmptyState
                icon={Building2}
                title="Aucun fournisseur"
                description="Aucun fournisseur n'a encore été enregistré."
                actionLabel={canCreate ? "Nouveau fournisseur" : undefined}
                onAction={canCreate ? () => setDialogOpen(true) : undefined}
              />
            }
          />
        )}
      </CardContent>

      <CreateSupplierDialog open={dialogOpen} onOpenChange={setDialogOpen} />
    </Card>
  );
}

function CreateSupplierDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const [nom, setNom] = useState("");
  const [contact, setContact] = useState("");
  const [conditions, setConditions] = useState("");
  const [error, setError] = useState<string | null>(null);
  const createSupplier = useCreateSupplier();

  useEffect(() => {
    if (!open) {
      setNom("");
      setContact("");
      setConditions("");
      setError(null);
    }
  }, [open]);

  function handleSubmit() {
    if (!nom.trim()) return;
    setError(null);
    createSupplier.mutate(
      { nom: nom.trim(), contact: contact.trim() || undefined, conditions_commerciales: conditions.trim() || undefined },
      {
        onSuccess: () => onOpenChange(false),
        onError: (err) => setError(apiErrorMessage(err)),
      },
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Nouveau fournisseur</DialogTitle>
          <DialogDescription>Enregistrez un fournisseur pour vos commandes d'achat.</DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div>
            <Label>Nom</Label>
            <input
              value={nom}
              onChange={(e) => setNom(e.target.value)}
              className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              placeholder="ex. Pharma Distribution SARL"
            />
          </div>
          <div>
            <Label>Contact</Label>
            <input
              value={contact}
              onChange={(e) => setContact(e.target.value)}
              className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              placeholder="téléphone, email..."
            />
          </div>
          <div>
            <Label>Conditions commerciales</Label>
            <textarea
              value={conditions}
              onChange={(e) => setConditions(e.target.value)}
              rows={3}
              className="w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              placeholder="délais de paiement, remises..."
            />
          </div>

          {error && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>
          )}
        </div>

        <DialogFooter>
          <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={createSupplier.isPending}>
            Annuler
          </Button>
          <Button onClick={handleSubmit} disabled={!nom.trim() || createSupplier.isPending}>
            {createSupplier.isPending && <LoaderCircle size={16} className="animate-spin" />}
            Créer
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
