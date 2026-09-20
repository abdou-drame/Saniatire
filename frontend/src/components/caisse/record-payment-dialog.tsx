import { useQuery } from "@tanstack/react-query";
import { CheckCircle2, LoaderCircle } from "lucide-react";
import { useEffect, useState } from "react";
import { Badge, type BadgeProps } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Label } from "@/components/ui/label";
import { Select } from "@/components/ui/select";
import { PatientPicker } from "@/components/clinical/patient-picker";
import { OpenSessionDialog } from "@/components/caisse/open-session-dialog";
import { useCreatePayment } from "@/hooks/use-payments";
import { useSites } from "@/hooks/use-sites";
import { api } from "@/lib/api";
import { apiErrorMessage } from "@/lib/api-error";
import { MODE_PAIEMENT_LABEL, STATUT_MOBILE_MONEY_LABEL } from "@/pages/caisse/caisse-status";
import type { Invoice, InvoiceStatus, ModePaiement, Paginated, Patient, StatutMobileMoney } from "@/types/api";

export interface RecordPaymentDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Optionnel — réutilisation future depuis l'écran Facturation. Fonctionne aussi en autonome. */
  presetInvoiceId?: number;
}

/** Message backend exact (422) — comparé verbatim pour proposer d'ouvrir une session. */
const NO_OPEN_SESSION_MESSAGE =
  "Aucune session de caisse ouverte : impossible d'encaisser un paiement en espèces.";

// Purement cosmétique, local à ce dialogue — l'écran Facturation possède son
// propre mapping ; on ne dépend d'aucun fichier des autres agents.
const INVOICE_STATUS_LABEL: Record<InvoiceStatus, string> = {
  brouillon: "Brouillon",
  emise: "Émise",
  partiellement_payee: "Partiellement payée",
  payee: "Payée",
  annulee: "Annulée",
};

const INVOICE_STATUS_BADGE: Record<InvoiceStatus, NonNullable<BadgeProps["status"]>> = {
  brouillon: "neutral",
  emise: "warning",
  partiellement_payee: "accent",
  payee: "success",
  annulee: "danger",
};

/**
 * Il n'existe pas d'endpoint de recherche libre de factures côté backend :
 * on choisit un patient via PatientPicker puis on liste ses factures via
 * GET /invoices?patient_id=, en filtrant côté client les statuts payée/annulée.
 * Requête auto-suffisante (pas de dépendance vers hooks/use-invoices.ts, propriété
 * de l'agent Facturation, qui peut ne pas encore exister au moment de l'exécution).
 */
function usePatientPayableInvoices(patientId: number | undefined) {
  return useQuery({
    queryKey: ["caisse", "patient-invoices", patientId],
    queryFn: async () => {
      const { data } = await api.get<Paginated<Invoice>>("/invoices", { params: { patient_id: patientId } });
      return data.data.filter((invoice) => invoice.statut !== "payee" && invoice.statut !== "annulee");
    },
    enabled: Boolean(patientId),
  });
}

function useInvoiceById(invoiceId: number | undefined) {
  return useQuery({
    queryKey: ["caisse", "invoice", invoiceId],
    queryFn: async () => {
      const { data } = await api.get<{ data: Invoice }>(`/invoices/${invoiceId}`);
      return data.data;
    },
    enabled: Boolean(invoiceId),
  });
}

export function RecordPaymentDialog({ open, onOpenChange, presetInvoiceId }: RecordPaymentDialogProps) {
  const [patient, setPatient] = useState<Patient | null>(null);
  const [selectedInvoice, setSelectedInvoice] = useState<Invoice | null>(null);
  const [montant, setMontant] = useState("");
  const [siteId, setSiteId] = useState<number | null>(null);
  const [modePaiement, setModePaiement] = useState<ModePaiement>("especes");
  const [referenceTransaction, setReferenceTransaction] = useState("");
  const [statutMobileMoney, setStatutMobileMoney] = useState<StatutMobileMoney | "">("");
  const [error, setError] = useState<string | null>(null);
  const [noSessionError, setNoSessionError] = useState(false);
  const [openSessionDialogOpen, setOpenSessionDialogOpen] = useState(false);
  const [created, setCreated] = useState<{ id: number; numero_recu: string | null } | null>(null);

  const sitesQuery = useSites();
  const presetInvoiceQuery = useInvoiceById(presetInvoiceId);
  const patientInvoicesQuery = usePatientPayableInvoices(presetInvoiceId ? undefined : patient?.id);
  const createPayment = useCreatePayment();

  useEffect(() => {
    if (!open) {
      setPatient(null);
      setSelectedInvoice(null);
      setMontant("");
      setSiteId(null);
      setModePaiement("especes");
      setReferenceTransaction("");
      setStatutMobileMoney("");
      setError(null);
      setNoSessionError(false);
      setOpenSessionDialogOpen(false);
      setCreated(null);
    }
  }, [open]);

  // Préremplit le site depuis la facture sélectionnée, reste modifiable.
  useEffect(() => {
    if (selectedInvoice) {
      setSiteId((prev) => prev ?? selectedInvoice.site_id);
    }
  }, [selectedInvoice]);

  useEffect(() => {
    if (presetInvoiceId && presetInvoiceQuery.data) {
      setSelectedInvoice(presetInvoiceQuery.data);
    }
  }, [presetInvoiceId, presetInvoiceQuery.data]);

  const invoice = selectedInvoice;
  // reference_transaction / statut_mobile_money sont optionnels même en mode
  // mobile_money : rien à valider de plus ici que montant/site/facture.
  const canSubmit = Boolean(invoice) && Boolean(siteId) && montant.trim() !== "" && Number(montant) > 0;

  function handleSubmit() {
    if (!invoice || !siteId || montant.trim() === "") return;
    setError(null);
    setNoSessionError(false);
    createPayment.mutate(
      {
        invoice_id: invoice.id,
        site_id: siteId,
        mode_paiement: modePaiement,
        reference_transaction: modePaiement === "mobile_money" ? referenceTransaction || null : null,
        statut_mobile_money: modePaiement === "mobile_money" ? statutMobileMoney || null : null,
        montant: Number(montant),
      },
      {
        onSuccess: (payment) => setCreated({ id: payment.id, numero_recu: payment.numero_recu }),
        onError: (err) => {
          const message = apiErrorMessage(err);
          setError(message);
          setNoSessionError(message === NO_OPEN_SESSION_MESSAGE);
        },
      },
    );
  }

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Enregistrer un paiement</DialogTitle>
            <DialogDescription>
              Sélectionnez la facture à encaisser puis renseignez le mode de paiement.
            </DialogDescription>
          </DialogHeader>

          {created ? (
            <div className="space-y-4 py-4 text-center">
              <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success/10 text-success">
                <CheckCircle2 size={24} />
              </div>
              <div className="space-y-1">
                <p className="text-sm text-text">Paiement enregistré — #{created.id}</p>
                <p className="text-xs text-text-muted">
                  Reçu : <span className="font-tabular text-text">{created.numero_recu ?? "—"}</span>
                </p>
              </div>
            </div>
          ) : (
            <div className="space-y-4">
              {!presetInvoiceId && !invoice && (
                <div className="space-y-3">
                  <div>
                    <Label>Patient</Label>
                    <PatientPicker
                      value={patient}
                      onChange={(next) => {
                        setPatient(next);
                        setSelectedInvoice(null);
                      }}
                    />
                  </div>

                  {patient && (
                    <div className="space-y-2">
                      <Label className="mb-0">Factures à encaisser</Label>
                      {patientInvoicesQuery.isLoading && (
                        <p className="text-xs text-text-muted">Chargement des factures…</p>
                      )}
                      {patientInvoicesQuery.isError && (
                        <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
                          {apiErrorMessage(patientInvoicesQuery.error)}
                        </p>
                      )}
                      {patientInvoicesQuery.data && patientInvoicesQuery.data.length === 0 && (
                        <p className="text-xs text-text-muted">
                          Aucune facture à encaisser pour ce patient.
                        </p>
                      )}
                      {patientInvoicesQuery.data && patientInvoicesQuery.data.length > 0 && (
                        <div className="divide-y divide-border rounded-md border border-border">
                          {patientInvoicesQuery.data.map((inv) => (
                            <button
                              type="button"
                              key={inv.id}
                              onClick={() => setSelectedInvoice(inv)}
                              className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-surface-hover"
                            >
                              <div>
                                <p className="text-text">{inv.numero}</p>
                                <p className="font-tabular text-xs text-text-muted">
                                  {Number(inv.montant_total).toLocaleString("fr-FR")}
                                </p>
                              </div>
                              <Badge status={INVOICE_STATUS_BADGE[inv.statut]}>
                                {INVOICE_STATUS_LABEL[inv.statut]}
                              </Badge>
                            </button>
                          ))}
                        </div>
                      )}
                    </div>
                  )}
                </div>
              )}

              {presetInvoiceId && presetInvoiceQuery.isLoading && (
                <p className="text-xs text-text-muted">Chargement de la facture…</p>
              )}

              {invoice && (
                <>
                  <div className="flex items-center justify-between gap-3 rounded-md border border-border bg-surface-hover/50 px-3 py-2">
                    <div>
                      <p className="text-sm text-text">{invoice.numero}</p>
                      <p className="font-tabular text-xs text-text-muted">
                        Montant total : {Number(invoice.montant_total).toLocaleString("fr-FR")}
                      </p>
                    </div>
                    <div className="flex items-center gap-2">
                      <Badge status={INVOICE_STATUS_BADGE[invoice.statut]}>
                        {INVOICE_STATUS_LABEL[invoice.statut]}
                      </Badge>
                      {!presetInvoiceId && (
                        <Button variant="ghost" size="sm" onClick={() => setSelectedInvoice(null)}>
                          Changer
                        </Button>
                      )}
                    </div>
                  </div>

                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                      <Label>Montant encaissé</Label>
                      <input
                        type="number"
                        min={0.01}
                        step="0.01"
                        value={montant}
                        onChange={(e) => setMontant(e.target.value)}
                        className="h-9 w-full rounded-md border border-border bg-bg px-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                      />
                    </div>
                    <div>
                      <Label>Site</Label>
                      <Select
                        value={siteId ?? ""}
                        onChange={(e) => setSiteId(e.target.value ? Number(e.target.value) : null)}
                        disabled={sitesQuery.isLoading}
                      >
                        <option value="">Sélectionner un site</option>
                        {(sitesQuery.data ?? []).map((site) => (
                          <option key={site.id} value={site.id}>
                            {site.name}
                          </option>
                        ))}
                      </Select>
                    </div>
                  </div>

                  <div>
                    <Label>Mode de paiement</Label>
                    <Select
                      value={modePaiement}
                      onChange={(e) => setModePaiement(e.target.value as ModePaiement)}
                    >
                      {(Object.keys(MODE_PAIEMENT_LABEL) as ModePaiement[]).map((mode) => (
                        <option key={mode} value={mode}>
                          {MODE_PAIEMENT_LABEL[mode]}
                        </option>
                      ))}
                    </Select>
                  </div>

                  {modePaiement === "mobile_money" && (
                    <div className="space-y-3 rounded-md border border-border p-3">
                      <p className="text-xs text-text-muted">
                        Mobile money : statut déclaré manuellement, aucune intégration de paiement réelle
                        n'est effectuée.
                      </p>
                      <div>
                        <Label>Référence de transaction</Label>
                        <input
                          type="text"
                          value={referenceTransaction}
                          onChange={(e) => setReferenceTransaction(e.target.value)}
                          placeholder="Référence communiquée par le patient"
                          className="h-9 w-full rounded-md border border-border bg-bg px-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                        />
                      </div>
                      <div>
                        <Label>Statut mobile money</Label>
                        <Select
                          value={statutMobileMoney}
                          onChange={(e) => setStatutMobileMoney(e.target.value as StatutMobileMoney | "")}
                        >
                          <option value="">Non renseigné</option>
                          {(Object.keys(STATUT_MOBILE_MONEY_LABEL) as StatutMobileMoney[]).map((statut) => (
                            <option key={statut} value={statut}>
                              {STATUT_MOBILE_MONEY_LABEL[statut]}
                            </option>
                          ))}
                        </Select>
                      </div>
                    </div>
                  )}
                </>
              )}

              {error && (
                <div className="space-y-2">
                  <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
                    {error}
                  </p>
                  {noSessionError && (
                    <Button variant="secondary" size="sm" onClick={() => setOpenSessionDialogOpen(true)}>
                      Ouvrir une session de caisse
                    </Button>
                  )}
                </div>
              )}
            </div>
          )}

          <DialogFooter>
            {created ? (
              <Button onClick={() => onOpenChange(false)}>Fermer</Button>
            ) : (
              <>
                <Button variant="secondary" onClick={() => onOpenChange(false)} disabled={createPayment.isPending}>
                  Annuler
                </Button>
                <Button onClick={handleSubmit} disabled={!canSubmit || createPayment.isPending}>
                  {createPayment.isPending && <LoaderCircle size={16} className="animate-spin" />}
                  Encaisser
                </Button>
              </>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <OpenSessionDialog open={openSessionDialogOpen} onOpenChange={setOpenSessionDialogOpen} />
    </>
  );
}
