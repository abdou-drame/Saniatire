import { Building2, CheckCircle2, LoaderCircle, Plus, ShieldCheck } from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { Badge, type BadgeProps } from "@/components/ui/badge";
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
import { Select } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/loading-state";
import { PatientPicker } from "@/components/clinical/patient-picker";
import { useAuth } from "@/hooks/use-auth";
import {
  useCreateCoverageRule,
  useCreateInsuranceConvention,
  useCreateInsuranceProvider,
  useCreatePatientInsuranceCoverage,
  useInsuranceConventions,
  useInsuranceProviders,
  usePatientInsuranceCoverages,
} from "@/hooks/use-insurance";
import { apiErrorMessage } from "@/lib/api-error";
import { formatDate } from "@/lib/datetime";
import type {
  BeneficiaireType,
  InsuranceConvention,
  InsuranceProvider,
  InsuranceProviderType,
  Patient,
  PatientInsuranceCoverage,
} from "@/types/api";

const PROVIDER_TYPE_LABEL: Record<InsuranceProviderType, string> = {
  assurance_privee: "Assurance privée",
  ipm: "IPM",
  mutuelle: "Mutuelle",
};

const PROVIDER_TYPE_BADGE: Record<InsuranceProviderType, NonNullable<BadgeProps["status"]>> = {
  assurance_privee: "accent",
  ipm: "accent2",
  mutuelle: "neutral",
};

const BENEFICIAIRE_TYPE_LABEL: Record<BeneficiaireType, string> = {
  assure_principal: "Assuré principal",
  ayant_droit: "Ayant droit",
};

function todayIsoDate(): string {
  return new Date().toISOString().slice(0, 10);
}

/**
 * Écran Assurances/IPM — organismes assureurs, leurs conventions et règles
 * de couverture (taux/plafond/exclusion), puis assignation de couvertures à
 * des patients. Les règles de couverture affichées ici sont uniquement des
 * données de CONFIGURATION (ce que le backend appliquera plus tard via
 * InsuranceCoverageService::computeSplit()) — aucun montant de prise en
 * charge hypothétique n'est jamais simulé ou calculé côté frontend.
 */
export function AssurancesPage() {
  const { hasPermission } = useAuth();
  const canCreate = hasPermission("assurance.create");

  const providersQuery = useInsuranceProviders();
  const [selectedProvider, setSelectedProvider] = useState<InsuranceProvider | null>(null);
  const [createProviderOpen, setCreateProviderOpen] = useState(false);

  const providerColumns: DataTableColumn<InsuranceProvider>[] = [
    { key: "nom", header: "Nom", accessor: (row) => row.nom, sortable: true },
    {
      key: "type",
      header: "Type",
      render: (row) => <Badge status={PROVIDER_TYPE_BADGE[row.type]}>{PROVIDER_TYPE_LABEL[row.type]}</Badge>,
    },
    { key: "contact", header: "Contact", render: (row) => row.contact ?? "—" },
    {
      key: "conventions_count",
      header: "Conventions",
      align: "right",
      accessor: (row) => row.conventions?.length ?? 0,
    },
  ];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-heading text-xl font-semibold text-text">Assurances / IPM</h1>
        <p className="mt-1 text-sm text-text-muted">
          Organismes assureurs, conventions, règles de couverture et couvertures patients.
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Organismes assureurs</CardTitle>
          {canCreate && (
            <Button size="sm" onClick={() => setCreateProviderOpen(true)}>
              <Plus size={14} />
              Nouvel organisme
            </Button>
          )}
        </CardHeader>
        <CardContent className="space-y-4">
          {providersQuery.isError ? (
            <ErrorState message={apiErrorMessage(providersQuery.error)} onRetry={() => providersQuery.refetch()} />
          ) : (
            <DataTable
              columns={providerColumns}
              data={providersQuery.data?.data ?? []}
              rowKey={(row) => row.id}
              isLoading={providersQuery.isLoading}
              onRowClick={(row) => setSelectedProvider(row)}
              emptyState={
                <EmptyState
                  icon={Building2}
                  title="Aucun organisme assureur"
                  description="Aucun organisme assureur n'a encore été enregistré."
                  actionLabel={canCreate ? "Nouvel organisme" : undefined}
                  onAction={canCreate ? () => setCreateProviderOpen(true) : undefined}
                />
              }
            />
          )}

          {selectedProvider && (
            <ProviderConventionsPanel
              provider={selectedProvider}
              canCreate={canCreate}
              onClose={() => setSelectedProvider(null)}
            />
          )}
        </CardContent>
      </Card>

      <PatientCoverageSection canCreate={canCreate} />

      <CreateProviderDialog open={createProviderOpen} onOpenChange={setCreateProviderOpen} />
    </div>
  );
}

function ProviderConventionsPanel({
  provider,
  canCreate,
  onClose,
}: {
  provider: InsuranceProvider;
  canCreate: boolean;
  onClose: () => void;
}) {
  const conventionsQuery = useInsuranceConventions(provider.id);
  const [createConventionOpen, setCreateConventionOpen] = useState(false);
  const [ruleConvention, setRuleConvention] = useState<InsuranceConvention | null>(null);

  const conventions = conventionsQuery.data?.data ?? [];

  return (
    <div className="space-y-4 rounded-md border border-border p-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-text">{provider.nom}</p>
          <p className="text-xs text-text-muted">{PROVIDER_TYPE_LABEL[provider.type]} · Conventions</p>
        </div>
        <div className="flex items-center gap-2">
          {canCreate && (
            <Button size="sm" variant="secondary" onClick={() => setCreateConventionOpen(true)}>
              <Plus size={14} />
              Nouvelle convention
            </Button>
          )}
          <Button variant="ghost" size="sm" onClick={onClose}>
            Fermer
          </Button>
        </div>
      </div>

      {conventionsQuery.isError ? (
        <ErrorState message={apiErrorMessage(conventionsQuery.error)} onRetry={() => conventionsQuery.refetch()} />
      ) : conventionsQuery.isLoading ? (
        <Skeleton className="h-24 w-full" />
      ) : conventions.length === 0 ? (
        <p className="text-xs text-text-muted">Aucune convention pour cet organisme.</p>
      ) : (
        <div className="space-y-3">
          {conventions.map((convention) => (
            <div key={convention.id} className="space-y-2 rounded-md border border-border bg-surface p-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="text-sm text-text">{convention.nom}</p>
                  <p className="text-xs text-text-muted">
                    {formatDate(convention.date_debut)} →{" "}
                    {convention.date_fin ? formatDate(convention.date_fin) : "Indéterminée"}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <Badge status={convention.actif ? "success" : "neutral"}>
                    {convention.actif ? "Active" : "Inactive"}
                  </Badge>
                  {canCreate && (
                    <Button size="sm" variant="secondary" onClick={() => setRuleConvention(convention)}>
                      <Plus size={14} />
                      Ajouter une règle de couverture
                    </Button>
                  )}
                </div>
              </div>

              {(convention.coverage_rules ?? []).length === 0 ? (
                <p className="text-xs text-text-muted">Aucune règle de couverture configurée.</p>
              ) : (
                <div className="divide-y divide-border rounded-md border border-border">
                  {convention.coverage_rules!.map((rule) => (
                    <div key={rule.id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2 text-xs">
                      <span className="text-text">{rule.categorie}</span>
                      <span className="font-tabular text-text-muted">{rule.taux_couverture}%</span>
                      <span className="font-tabular text-text-muted">
                        {rule.plafond_montant != null
                          ? `Plafond ${Number(rule.plafond_montant).toLocaleString("fr-FR")}`
                          : "Sans plafond"}
                      </span>
                      {rule.exclu && <Badge status="danger">Exclu</Badge>}
                    </div>
                  ))}
                </div>
              )}
            </div>
          ))}
        </div>
      )}

      <CreateConventionDialog
        providerId={provider.id}
        open={createConventionOpen}
        onOpenChange={setCreateConventionOpen}
      />
      {ruleConvention && (
        <CreateCoverageRuleDialog
          convention={ruleConvention}
          open={Boolean(ruleConvention)}
          onOpenChange={(open) => !open && setRuleConvention(null)}
        />
      )}
    </div>
  );
}

function CreateProviderDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const [nom, setNom] = useState("");
  const [type, setType] = useState<InsuranceProviderType>("assurance_privee");
  const [contact, setContact] = useState("");
  const [error, setError] = useState<string | null>(null);
  const createProvider = useCreateInsuranceProvider();

  useEffect(() => {
    if (!open) {
      setNom("");
      setType("assurance_privee");
      setContact("");
      setError(null);
    }
  }, [open]);

  function handleSubmit() {
    if (!nom.trim()) return;
    setError(null);
    createProvider.mutate(
      { nom: nom.trim(), type, contact: contact.trim() || undefined },
      { onSuccess: () => onOpenChange(false), onError: (err) => setError(apiErrorMessage(err)) },
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Nouvel organisme assureur</DialogTitle>
          <DialogDescription>Assurance privée, IPM ou mutuelle.</DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div>
            <Label>Nom</Label>
            <input
              value={nom}
              onChange={(e) => setNom(e.target.value)}
              className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              placeholder="ex. IPM Santé Sénégal"
            />
          </div>
          <div>
            <Label>Type</Label>
            <Select value={type} onChange={(e) => setType(e.target.value as InsuranceProviderType)}>
              <option value="assurance_privee">Assurance privée</option>
              <option value="ipm">IPM</option>
              <option value="mutuelle">Mutuelle</option>
            </Select>
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

          {error && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>
          )}
        </div>

        <DialogFooter>
          <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={createProvider.isPending}>
            Annuler
          </Button>
          <Button onClick={handleSubmit} disabled={!nom.trim() || createProvider.isPending}>
            {createProvider.isPending && <LoaderCircle size={16} className="animate-spin" />}
            Créer
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function CreateConventionDialog({
  providerId,
  open,
  onOpenChange,
}: {
  providerId: number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const [nom, setNom] = useState("");
  const [dateDebut, setDateDebut] = useState(todayIsoDate());
  const [dateFin, setDateFin] = useState("");
  const [actif, setActif] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const createConvention = useCreateInsuranceConvention();

  useEffect(() => {
    if (!open) {
      setNom("");
      setDateDebut(todayIsoDate());
      setDateFin("");
      setActif(true);
      setError(null);
    }
  }, [open]);

  const canSubmit = nom.trim() !== "" && dateDebut.trim() !== "";

  function handleSubmit() {
    if (!canSubmit) return;
    setError(null);
    createConvention.mutate(
      {
        insurance_provider_id: providerId,
        nom: nom.trim(),
        date_debut: dateDebut,
        date_fin: dateFin || undefined,
        actif,
      },
      { onSuccess: () => onOpenChange(false), onError: (err) => setError(apiErrorMessage(err)) },
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Nouvelle convention</DialogTitle>
          <DialogDescription>Convention rattachée à l'organisme sélectionné.</DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div>
            <Label>Nom</Label>
            <input
              value={nom}
              onChange={(e) => setNom(e.target.value)}
              className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              placeholder="ex. Convention entreprise 2026"
            />
          </div>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Date de début</Label>
              <input
                type="date"
                value={dateDebut}
                onChange={(e) => setDateDebut(e.target.value)}
                className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              />
            </div>
            <div>
              <Label>Date de fin (optionnelle)</Label>
              <input
                type="date"
                value={dateFin}
                onChange={(e) => setDateFin(e.target.value)}
                className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              />
            </div>
          </div>
          <label className="flex items-center gap-2 text-sm text-text">
            <input
              type="checkbox"
              checked={actif}
              onChange={(e) => setActif(e.target.checked)}
              className="h-4 w-4 rounded border-border-strong accent-accent"
            />
            Convention active
          </label>

          {error && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>
          )}
        </div>

        <DialogFooter>
          <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={createConvention.isPending}>
            Annuler
          </Button>
          <Button onClick={handleSubmit} disabled={!canSubmit || createConvention.isPending}>
            {createConvention.isPending && <LoaderCircle size={16} className="animate-spin" />}
            Créer
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function CreateCoverageRuleDialog({
  convention,
  open,
  onOpenChange,
}: {
  convention: InsuranceConvention;
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const [categorie, setCategorie] = useState("");
  const [tauxCouverture, setTauxCouverture] = useState("");
  const [plafondMontant, setPlafondMontant] = useState("");
  const [exclu, setExclu] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const createRule = useCreateCoverageRule();

  useEffect(() => {
    if (!open) {
      setCategorie("");
      setTauxCouverture("");
      setPlafondMontant("");
      setExclu(false);
      setError(null);
    }
  }, [open]);

  const tauxNumber = Number(tauxCouverture);
  const canSubmit =
    categorie.trim() !== "" && tauxCouverture.trim() !== "" && tauxNumber >= 0 && tauxNumber <= 100;

  function handleSubmit() {
    if (!canSubmit) return;
    setError(null);
    createRule.mutate(
      {
        conventionId: convention.id,
        categorie: categorie.trim(),
        taux_couverture: tauxNumber,
        plafond_montant: plafondMontant.trim() !== "" ? Number(plafondMontant) : undefined,
        exclu,
      },
      { onSuccess: () => onOpenChange(false), onError: (err) => setError(apiErrorMessage(err)) },
    );
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Ajouter une règle de couverture</DialogTitle>
          <DialogDescription>{convention.nom}</DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div>
            <Label>Catégorie de prestation</Label>
            <input
              value={categorie}
              onChange={(e) => setCategorie(e.target.value)}
              className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              placeholder="ex. consultation, hospitalisation, pharmacie..."
            />
          </div>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label>Taux de couverture (%)</Label>
              <input
                type="number"
                min={0}
                max={100}
                value={tauxCouverture}
                onChange={(e) => setTauxCouverture(e.target.value)}
                className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              />
            </div>
            <div>
              <Label>Plafond (optionnel)</Label>
              <input
                type="number"
                min={0}
                step="0.01"
                value={plafondMontant}
                onChange={(e) => setPlafondMontant(e.target.value)}
                className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                placeholder="Sans plafond"
              />
            </div>
          </div>
          <label className="flex items-center gap-2 text-sm text-text">
            <input
              type="checkbox"
              checked={exclu}
              onChange={(e) => setExclu(e.target.checked)}
              className="h-4 w-4 rounded border-border-strong accent-accent"
            />
            Catégorie exclue (non couverte, quel que soit le taux)
          </label>

          {error && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{error}</p>
          )}
        </div>

        <DialogFooter>
          <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={createRule.isPending}>
            Annuler
          </Button>
          <Button onClick={handleSubmit} disabled={!canSubmit || createRule.isPending}>
            {createRule.isPending && <LoaderCircle size={16} className="animate-spin" />}
            Ajouter
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

function PatientCoverageSection({ canCreate }: { canCreate: boolean }) {
  const [patient, setPatient] = useState<Patient | null>(null);
  const [conventionId, setConventionId] = useState<number | null>(null);
  const [numeroAdherent, setNumeroAdherent] = useState("");
  const [beneficiaireType, setBeneficiaireType] = useState<BeneficiaireType>("assure_principal");
  const [dateDebut, setDateDebut] = useState(todayIsoDate());
  const [dateFin, setDateFin] = useState("");
  const [actif, setActif] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  const providersQuery = useInsuranceProviders();
  const conventionsQuery = useInsuranceConventions();
  const coveragesQuery = usePatientInsuranceCoverages(patient?.id);
  const createCoverage = useCreatePatientInsuranceCoverage();

  const providerNameById = useMemo(() => {
    const map = new Map<number, string>();
    for (const provider of providersQuery.data?.data ?? []) {
      map.set(provider.id, provider.nom);
    }
    return map;
  }, [providersQuery.data]);

  const conventionOptions = useMemo(
    () =>
      (conventionsQuery.data?.data ?? []).map((convention) => ({
        id: convention.id,
        label: `${providerNameById.get(convention.insurance_provider_id) ?? "?"} — ${convention.nom}`,
      })),
    [conventionsQuery.data, providerNameById],
  );

  function resetForm() {
    setConventionId(null);
    setNumeroAdherent("");
    setBeneficiaireType("assure_principal");
    setDateDebut(todayIsoDate());
    setDateFin("");
    setActif(true);
    setSuccess(false);
  }

  function handlePatientChange(next: Patient | null) {
    setPatient(next);
    resetForm();
    setError(null);
  }

  const canSubmit = Boolean(patient) && Boolean(conventionId) && numeroAdherent.trim() !== "" && dateDebut.trim() !== "";

  function handleSubmit() {
    if (!patient || !conventionId) return;
    setError(null);
    setSuccess(false);
    createCoverage.mutate(
      {
        patient_id: patient.id,
        insurance_convention_id: conventionId,
        numero_adherent: numeroAdherent.trim(),
        beneficiaire_type: beneficiaireType,
        date_debut: dateDebut,
        date_fin: dateFin || undefined,
        actif,
      },
      {
        onSuccess: () => {
          setSuccess(true);
          setConventionId(null);
          setNumeroAdherent("");
        },
        onError: (err) => setError(apiErrorMessage(err)),
      },
    );
  }

  const coverageColumns: DataTableColumn<PatientInsuranceCoverage>[] = [
    {
      key: "convention",
      header: "Convention",
      render: (row) => (row.convention ? `${row.convention.provider_nom ?? "—"} — ${row.convention.nom}` : "—"),
    },
    { key: "numero_adherent", header: "N° adhérent", accessor: (row) => row.numero_adherent },
    {
      key: "beneficiaire_type",
      header: "Bénéficiaire",
      render: (row) => BENEFICIAIRE_TYPE_LABEL[row.beneficiaire_type],
    },
    {
      key: "periode",
      header: "Période",
      render: (row) => `${formatDate(row.date_debut)} → ${row.date_fin ? formatDate(row.date_fin) : "Indéterminée"}`,
    },
    {
      key: "actif",
      header: "Statut",
      render: (row) => <Badge status={row.actif ? "success" : "neutral"}>{row.actif ? "Active" : "Inactive"}</Badge>,
    },
  ];

  return (
    <Card>
      <CardHeader>
        <CardTitle>Couverture patients</CardTitle>
      </CardHeader>
      <CardContent className="space-y-4">
        <div>
          <Label>Patient</Label>
          <PatientPicker
            value={patient}
            onChange={handlePatientChange}
            placeholder="Rechercher un patient pour voir ou assigner ses couvertures..."
          />
        </div>

        {canCreate && patient && (
          <div className="space-y-3 rounded-md border border-border p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-text-subtle">Assigner une couverture</p>

            {success ? (
              <div className="flex items-center gap-2 rounded-md border border-success/30 bg-success/10 px-3 py-2 text-xs text-success">
                <CheckCircle2 size={14} />
                Couverture assignée.
              </div>
            ) : (
              <>
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <div>
                    <Label>Convention</Label>
                    <Select
                      value={conventionId ?? ""}
                      onChange={(e) => setConventionId(e.target.value ? Number(e.target.value) : null)}
                      disabled={conventionsQuery.isLoading}
                    >
                      <option value="">Sélectionner une convention</option>
                      {conventionOptions.map((option) => (
                        <option key={option.id} value={option.id}>
                          {option.label}
                        </option>
                      ))}
                    </Select>
                  </div>
                  <div>
                    <Label>N° adhérent</Label>
                    <input
                      value={numeroAdherent}
                      onChange={(e) => setNumeroAdherent(e.target.value)}
                      className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                    />
                  </div>
                  <div>
                    <Label>Type de bénéficiaire</Label>
                    <Select
                      value={beneficiaireType}
                      onChange={(e) => setBeneficiaireType(e.target.value as BeneficiaireType)}
                    >
                      <option value="assure_principal">Assuré principal</option>
                      <option value="ayant_droit">Ayant droit</option>
                    </Select>
                  </div>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <Label>Date de début</Label>
                      <input
                        type="date"
                        value={dateDebut}
                        onChange={(e) => setDateDebut(e.target.value)}
                        className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                      />
                    </div>
                    <div>
                      <Label>Date de fin (optionnelle)</Label>
                      <input
                        type="date"
                        value={dateFin}
                        onChange={(e) => setDateFin(e.target.value)}
                        className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                      />
                    </div>
                  </div>
                </div>

                <label className="flex items-center gap-2 text-sm text-text">
                  <input
                    type="checkbox"
                    checked={actif}
                    onChange={(e) => setActif(e.target.checked)}
                    className="h-4 w-4 rounded border-border-strong accent-accent"
                  />
                  Couverture active
                </label>

                {error && (
                  <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
                    {error}
                  </p>
                )}

                <div className="flex justify-end">
                  <Button size="sm" onClick={handleSubmit} disabled={!canSubmit || createCoverage.isPending}>
                    {createCoverage.isPending && <LoaderCircle size={14} className="animate-spin" />}
                    Assigner la couverture
                  </Button>
                </div>
              </>
            )}
          </div>
        )}

        {patient ? (
          coveragesQuery.isError ? (
            <ErrorState message={apiErrorMessage(coveragesQuery.error)} onRetry={() => coveragesQuery.refetch()} />
          ) : (
            <DataTable
              columns={coverageColumns}
              data={coveragesQuery.data?.data ?? []}
              rowKey={(row) => row.id}
              isLoading={coveragesQuery.isLoading}
              emptyState={
                <EmptyState
                  icon={ShieldCheck}
                  title="Aucune couverture"
                  description="Ce patient n'a aucune couverture d'assurance enregistrée."
                />
              }
            />
          )
        ) : (
          <p className="text-sm text-text-muted">Sélectionnez un patient pour voir ou assigner ses couvertures.</p>
        )}
      </CardContent>
    </Card>
  );
}
