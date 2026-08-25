import { useState } from "react";
import { ArrowLeft, CheckCircle2, FlaskConical, LoaderCircle, Scan, Search, X } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { ErrorState } from "@/components/ui/error-state";
import { Select } from "@/components/ui/select";
import { Skeleton } from "@/components/ui/loading-state";
import {
  usePrescriberLoincSearch,
  usePrescriberPatientSearch,
  usePrescriberSites,
} from "@/hooks/portal-prescripteur/use-prescriber-catalog";
import {
  useCreatePrescriberImagingOrder,
  useCreatePrescriberLabOrder,
} from "@/hooks/portal-prescripteur/use-prescriber-requests";
import { useDebouncedValue } from "@/hooks/use-debounced-value";
import { age } from "@/lib/datetime";
import { portalErrorMessage } from "@/lib/portal-error";
import type { PrescriberLoincCode, PrescriberPatientSearchResult } from "@/types/api";

type ExamKind = "labo" | "imagerie";

const EXAM_TYPE_OPTIONS: { value: "radio" | "echo" | "scanner" | "irm"; label: string }[] = [
  { value: "radio", label: "Radiographie" },
  { value: "echo", label: "Échographie" },
  { value: "scanner", label: "Scanner" },
  { value: "irm", label: "IRM" },
];

export function PrescriberNewRequestPage() {
  const navigate = useNavigate();
  const [kind, setKind] = useState<ExamKind>("labo");
  const [patient, setPatient] = useState<PrescriberPatientSearchResult | null>(null);
  const [patientSearch, setPatientSearch] = useState("");
  const [siteId, setSiteId] = useState<number | null>(null);
  const [examType, setExamType] = useState<"radio" | "echo" | "scanner" | "irm">("radio");
  const [loincSearch, setLoincSearch] = useState("");
  const [selectedCodes, setSelectedCodes] = useState<PrescriberLoincCode[]>([]);
  const [notes, setNotes] = useState("");
  const [notice, setNotice] = useState<string | null>(null);
  const [confirmation, setConfirmation] = useState<{ kind: ExamKind; id: number } | null>(null);

  const debouncedPatientSearch = useDebouncedValue(patientSearch, 300);
  const debouncedLoincSearch = useDebouncedValue(loincSearch, 300);

  const sitesQuery = usePrescriberSites();
  const patientResultsQuery = usePrescriberPatientSearch(patient ? "" : debouncedPatientSearch);
  const loincResultsQuery = usePrescriberLoincSearch(debouncedLoincSearch);

  const createLab = useCreatePrescriberLabOrder();
  const createImaging = useCreatePrescriberImagingOrder();
  const isPending = createLab.isPending || createImaging.isPending;

  function toggleLoincCode(code: PrescriberLoincCode) {
    setSelectedCodes((prev) =>
      prev.some((c) => c.id === code.id) ? prev.filter((c) => c.id !== code.id) : [...prev, code],
    );
  }

  function canSubmit(): boolean {
    if (!patient || !siteId) return false;
    if (kind === "labo") return selectedCodes.length > 0;
    return true;
  }

  function handleSubmit() {
    if (!patient || !siteId) return;
    setNotice(null);

    if (kind === "labo") {
      createLab.mutate(
        {
          patient_id: patient.id,
          site_id: siteId,
          notes: notes || undefined,
          items: selectedCodes.map((c) => ({ loinc_code_id: c.id })),
        },
        {
          onSuccess: (order) => setConfirmation({ kind: "labo", id: order.id }),
          onError: (error) => setNotice(portalErrorMessage(error)),
        },
      );
    } else {
      createImaging.mutate(
        { patient_id: patient.id, site_id: siteId, exam_type: examType, notes: notes || undefined },
        {
          onSuccess: (order) => setConfirmation({ kind: "imagerie", id: order.id }),
          onError: (error) => setNotice(portalErrorMessage(error)),
        },
      );
    }
  }

  if (confirmation) {
    return (
      <div className="mx-auto max-w-md space-y-6 py-8 text-center">
        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-success/10 text-success">
          <CheckCircle2 size={28} />
        </div>
        <div>
          <h2 className="font-heading text-lg font-semibold text-text">Demande envoyée</h2>
          <p className="mt-1 text-sm text-text-muted">
            Votre demande {confirmation.kind === "labo" ? "de laboratoire" : "d'imagerie"} a bien été transmise.
          </p>
        </div>
        <div className="rounded-lg border border-border bg-surface px-4 py-3">
          <p className="text-xs text-text-muted">Identifiant de suivi</p>
          <p className="font-heading text-xl font-semibold text-text">#{confirmation.id}</p>
        </div>
        <div className="flex justify-center gap-3">
          <Button variant="secondary" onClick={() => window.location.reload()}>
            Nouvelle demande
          </Button>
          <Button onClick={() => navigate("/portail-prescripteur/demandes", { replace: true })}>
            Voir mes demandes
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Link to="/portail-prescripteur/demandes" className="text-text-muted hover:text-text">
          <ArrowLeft size={18} />
        </Link>
        <h2 className="font-heading text-lg font-semibold text-text">Nouvelle demande</h2>
      </div>

      <Card>
        <CardContent className="space-y-5 pt-5">
          <div className="space-y-1.5">
            <label className="text-xs font-medium text-text-muted">Type d'examen</label>
            <div className="flex gap-2">
              <button
                type="button"
                onClick={() => setKind("labo")}
                className={`flex flex-1 items-center justify-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition-colors ${
                  kind === "labo"
                    ? "border-accent bg-accent/10 text-accent-light"
                    : "border-border bg-surface text-text-muted hover:bg-surface-hover"
                }`}
              >
                <FlaskConical size={15} /> Laboratoire
              </button>
              <button
                type="button"
                onClick={() => setKind("imagerie")}
                className={`flex flex-1 items-center justify-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition-colors ${
                  kind === "imagerie"
                    ? "border-accent bg-accent/10 text-accent-light"
                    : "border-border bg-surface text-text-muted hover:bg-surface-hover"
                }`}
              >
                <Scan size={15} /> Imagerie
              </button>
            </div>
          </div>

          <div className="space-y-1.5">
            <label className="text-xs font-medium text-text-muted">Patient</label>
            {patient ? (
              <div className="flex items-center justify-between gap-3 rounded-md border border-border bg-surface px-3 py-2">
                <div>
                  <p className="text-sm font-medium text-text">
                    {patient.first_name} {patient.last_name}
                  </p>
                  <p className="text-xs text-text-muted">
                    {patient.patient_number} · {age(patient.birth_date)} ans
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => {
                    setPatient(null);
                    setPatientSearch("");
                  }}
                  className="text-text-muted hover:text-text"
                >
                  <X size={16} />
                </button>
              </div>
            ) : (
              <div className="space-y-2">
                <div className="relative">
                  <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-text-subtle" />
                  <input
                    value={patientSearch}
                    onChange={(e) => setPatientSearch(e.target.value)}
                    placeholder="Nom, prénom ou numéro patient (2 caractères min.)"
                    className="h-9 w-full rounded-md border border-border bg-bg pl-8 pr-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                  />
                </div>
                {debouncedPatientSearch.trim().length >= 2 && (
                  <div className="rounded-md border border-border bg-surface">
                    {patientResultsQuery.isLoading ? (
                      <div className="space-y-1 p-2">
                        <Skeleton className="h-8" />
                        <Skeleton className="h-8" />
                      </div>
                    ) : patientResultsQuery.isError ? (
                      <p className="p-3 text-xs text-danger">{portalErrorMessage(patientResultsQuery.error)}</p>
                    ) : (patientResultsQuery.data ?? []).length === 0 ? (
                      <p className="p-3 text-xs text-text-muted">Aucun patient trouvé.</p>
                    ) : (
                      (patientResultsQuery.data ?? []).map((result) => (
                        <button
                          key={result.id}
                          type="button"
                          onClick={() => setPatient(result)}
                          className="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-surface-hover"
                        >
                          <span className="text-text">
                            {result.first_name} {result.last_name}
                          </span>
                          <span className="text-xs text-text-muted">{result.patient_number}</span>
                        </button>
                      ))
                    )}
                  </div>
                )}
              </div>
            )}
          </div>

          <div className="space-y-1.5">
            <label className="text-xs font-medium text-text-muted">Site</label>
            {sitesQuery.isError ? (
              <ErrorState message={portalErrorMessage(sitesQuery.error)} onRetry={() => sitesQuery.refetch()} />
            ) : (
              <Select
                value={siteId ?? ""}
                onChange={(e) => setSiteId(e.target.value ? Number(e.target.value) : null)}
                disabled={sitesQuery.isLoading}
              >
                <option value="">Choisissez un site</option>
                {(sitesQuery.data ?? []).map((site) => (
                  <option key={site.id} value={site.id}>
                    {site.name}
                  </option>
                ))}
              </Select>
            )}
          </div>

          {kind === "labo" ? (
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-text-muted">Analyses demandées</label>
              {selectedCodes.length > 0 && (
                <div className="flex flex-wrap gap-1.5">
                  {selectedCodes.map((code) => (
                    <span
                      key={code.id}
                      className="flex items-center gap-1.5 rounded-full border border-accent/30 bg-accent/10 px-2.5 py-0.5 text-xs text-accent-light"
                    >
                      {code.label}
                      <button type="button" onClick={() => toggleLoincCode(code)}>
                        <X size={12} />
                      </button>
                    </span>
                  ))}
                </div>
              )}
              <div className="relative">
                <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-text-subtle" />
                <input
                  value={loincSearch}
                  onChange={(e) => setLoincSearch(e.target.value)}
                  placeholder="Rechercher une analyse (ex : glycémie, NFS...)"
                  className="h-9 w-full rounded-md border border-border bg-bg pl-8 pr-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
                />
              </div>
              <div className="max-h-48 overflow-y-auto rounded-md border border-border bg-surface">
                {loincResultsQuery.isLoading ? (
                  <div className="space-y-1 p-2">
                    <Skeleton className="h-8" />
                    <Skeleton className="h-8" />
                  </div>
                ) : loincResultsQuery.isError ? (
                  <p className="p-3 text-xs text-danger">{portalErrorMessage(loincResultsQuery.error)}</p>
                ) : (loincResultsQuery.data ?? []).length === 0 ? (
                  <p className="p-3 text-xs text-text-muted">Aucune analyse trouvée.</p>
                ) : (
                  (loincResultsQuery.data ?? []).map((code) => {
                    const isSelected = selectedCodes.some((c) => c.id === code.id);
                    return (
                      <button
                        key={code.id}
                        type="button"
                        onClick={() => toggleLoincCode(code)}
                        className={`flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-surface-hover ${
                          isSelected ? "bg-accent/5" : ""
                        }`}
                      >
                        <span className="text-text">{code.label}</span>
                        <span className="text-xs text-text-muted">{code.code}</span>
                      </button>
                    );
                  })
                )}
              </div>
            </div>
          ) : (
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-text-muted">Type d'examen d'imagerie</label>
              <Select value={examType} onChange={(e) => setExamType(e.target.value as typeof examType)}>
                {EXAM_TYPE_OPTIONS.map((opt) => (
                  <option key={opt.value} value={opt.value}>
                    {opt.label}
                  </option>
                ))}
              </Select>
            </div>
          )}

          <div className="space-y-1.5">
            <label htmlFor="notes" className="text-xs font-medium text-text-muted">
              Motif
            </label>
            <textarea
              id="notes"
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              rows={3}
              className="w-full rounded-md border border-border bg-bg px-3 py-2 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              placeholder="Contexte clinique, motif de la demande..."
            />
          </div>

          {notice && (
            <p className="rounded-md border border-warning/30 bg-warning/10 px-3 py-2 text-xs text-warning">{notice}</p>
          )}

          <Button className="w-full" disabled={!canSubmit() || isPending} onClick={handleSubmit}>
            {isPending && <LoaderCircle size={16} className="animate-spin" />}
            Envoyer la demande
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
