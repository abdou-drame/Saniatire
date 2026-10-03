import { LoaderCircle, Tags } from "lucide-react";
import { useState, type FormEvent } from "react";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { EmptyState } from "@/components/ui/empty-state";
import { ErrorState } from "@/components/ui/error-state";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useCreatePlatformPlan, usePlatformPlans, useUpdatePlatformPlan } from "@/hooks/use-platform-subscriptions";
import { apiErrorMessage } from "@/lib/api-error";
import type { Plan } from "@/types/api";

function formatFcfa(value: number | null): string {
  return value === null ? "Sur devis" : `${value.toLocaleString("fr-FR")} FCFA`;
}

/** Champ vide = prix sur devis (null côté backend) ; undefined = saisie invalide. */
function parsePrice(value: string): number | null | undefined {
  const digits = value.replace(/\s/g, "");
  if (digits === "") return null;
  return /^\d+$/.test(digits) ? Number(digits) : undefined;
}

function PlanDialog({ plan, open, onOpenChange }: { plan: Plan | null; open: boolean; onOpenChange: (open: boolean) => void }) {
  const createPlan = useCreatePlatformPlan();
  const updatePlan = useUpdatePlatformPlan();
  const [code, setCode] = useState(plan?.code ?? "");
  const [name, setName] = useState(plan?.name ?? "");
  const [monthly, setMonthly] = useState(plan?.monthly_price_fcfa?.toString() ?? "");
  const [annual, setAnnual] = useState(plan?.annual_price_fcfa?.toString() ?? "");
  const [isActive, setIsActive] = useState(plan?.is_active ?? true);
  const [error, setError] = useState<string | null>(null);
  const isPending = createPlan.isPending || updatePlan.isPending;

  function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    const monthlyPrice = parsePrice(monthly);
    const annualPrice = parsePrice(annual);
    if (monthlyPrice === undefined || annualPrice === undefined) {
      setError("Les prix doivent être des nombres entiers en FCFA, sans point ni virgule (ex. 15000).");
      return;
    }
    const prices = { monthly_price_fcfa: monthlyPrice, annual_price_fcfa: annualPrice };
    const options = { onSuccess: () => onOpenChange(false), onError: (err: unknown) => setError(apiErrorMessage(err)) };
    if (plan) {
      updatePlan.mutate({ id: plan.id, name, ...prices, is_active: isActive }, options);
    } else {
      createPlan.mutate({ code, name, ...prices }, options);
    }
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{plan ? `Modifier la formule ${plan.name}` : "Nouvelle formule"}</DialogTitle>
          <DialogDescription>
            Laisser un prix vide signifie « sur devis ». Les périodes déjà enregistrées ne sont pas modifiées.
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="space-y-4">
          {error && <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-sm text-danger">{error}</p>}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <Label htmlFor="plan-code">Code</Label>
              <Input
                id="plan-code"
                value={code}
                onChange={(e) => setCode(e.target.value)}
                disabled={Boolean(plan)}
                placeholder="ex. pro"
                required
              />
            </div>
            <div>
              <Label htmlFor="plan-name">Nom</Label>
              <Input id="plan-name" value={name} onChange={(e) => setName(e.target.value)} placeholder="ex. Pro" required />
            </div>
            <div>
              <Label htmlFor="plan-monthly">Prix mensuel (FCFA)</Label>
              <Input
                id="plan-monthly"
                inputMode="numeric"
                value={monthly}
                onChange={(e) => setMonthly(e.target.value)}
                placeholder="Sur devis"
              />
            </div>
            <div>
              <Label htmlFor="plan-annual">Prix annuel (FCFA)</Label>
              <Input
                id="plan-annual"
                inputMode="numeric"
                value={annual}
                onChange={(e) => setAnnual(e.target.value)}
                placeholder="Sur devis"
              />
            </div>
          </div>
          {plan && (
            <label className="flex items-center gap-2 text-sm text-text">
              <input id="plan-active" type="checkbox" checked={isActive} onChange={(e) => setIsActive(e.target.checked)} />
              Proposée pour les nouvelles périodes
            </label>
          )}
          {plan && <p className="text-xs text-text-subtle">Le code ne peut pas être modifié.</p>}
          <DialogFooter>
            <Button type="button" variant="ghost" onClick={() => onOpenChange(false)}>
              Annuler
            </Button>
            <Button type="submit" disabled={isPending}>
              {isPending && <LoaderCircle size={14} className="animate-spin" />}
              {plan ? "Enregistrer" : "Créer la formule"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

/**
 * Grille des formules : référence commerciale pour les périodes
 * d'abonnement (aucun paiement, aucun lien avec les modules).
 */
export function PlatformPlansPage() {
  const { data: plans, isLoading, isError, error, refetch } = usePlatformPlans();
  const [dialog, setDialog] = useState<{ plan: Plan | null; key: number } | null>(null);

  const openDialog = (plan: Plan | null) => setDialog({ plan, key: Date.now() });

  const columns: DataTableColumn<Plan>[] = [
    { key: "name", header: "Formule", accessor: (p) => p.name },
    { key: "code", header: "Code", accessor: (p) => p.code },
    { key: "monthly", header: "Mensuel", accessor: (p) => formatFcfa(p.monthly_price_fcfa) },
    { key: "annual", header: "Annuel", accessor: (p) => formatFcfa(p.annual_price_fcfa) },
    {
      key: "is_active",
      header: "Statut",
      render: (p) => <Badge status={p.is_active ? "success" : "neutral"}>{p.is_active ? "Proposée" : "Retirée"}</Badge>,
    },
  ];

  return (
    <div className="space-y-6">
      <Card>
        <CardHeader>
          <CardTitle>Formules</CardTitle>
          <Button onClick={() => openDialog(null)}>Nouvelle formule</Button>
        </CardHeader>
        <CardContent>
          {isError ? (
            <ErrorState message={apiErrorMessage(error)} onRetry={() => refetch()} />
          ) : (
            <DataTable
              columns={columns}
              data={plans ?? []}
              rowKey={(p) => p.id}
              isLoading={isLoading}
              onRowClick={(p) => openDialog(p)}
              emptyState={<EmptyState icon={Tags} title="Aucune formule" actionLabel="Nouvelle formule" onAction={() => openDialog(null)} />}
            />
          )}
          <p className="mt-3 text-xs text-text-subtle">Cliquez sur une formule pour la modifier.</p>
        </CardContent>
      </Card>

      {dialog && (
        <PlanDialog
          key={dialog.key}
          plan={dialog.plan}
          open
          onOpenChange={(open) => !open && setDialog(null)}
        />
      )}
    </div>
  );
}
