import { BedDouble, FlaskConical, History, Scan, Stethoscope, Syringe } from "lucide-react";
import { Badge } from "@/components/ui/badge";
import { EmptyState } from "@/components/ui/empty-state";
import { formatDate, formatDateTime } from "@/lib/datetime";
import { specialtyUiMeta } from "@/lib/specialty-registry";
import { EXAM_TYPE_LABEL } from "@/pages/imagerie/imaging-status";
import type { Consultation, Hospitalization, ImagingOrder, LabOrder, SurgicalProcedure, TimelineEvent } from "@/types/api";

export function PatientTimeline({ events }: { events: TimelineEvent[] }) {
  if (events.length === 0) {
    return (
      <EmptyState
        icon={History}
        title="Aucun événement"
        description="L'historique du patient (consultations, activité) apparaîtra ici."
      />
    );
  }

  return (
    <ol className="space-y-4">
      {events.map((event, index) => {
        const consultation = event.type === "consultation" ? (event.data as Consultation) : null;
        const labOrder = event.type === "lab_order" ? (event.data as LabOrder) : null;
        const imagingOrder = event.type === "imaging_order" ? (event.data as ImagingOrder) : null;
        const hospitalization = event.type === "hospitalization" ? (event.data as Hospitalization) : null;
        const surgicalProcedure = event.type === "surgical_procedure" ? (event.data as SurgicalProcedure) : null;
        const specialtyMeta = consultation ? specialtyUiMeta(consultation.specialty_type) : null;
        const SpecialtyIcon = specialtyMeta?.icon;

        return (
          <li key={`${event.type}-${index}`} className="flex gap-3">
            <div className="flex flex-col items-center">
              <div
                className={
                  specialtyMeta
                    ? `flex h-7 w-7 shrink-0 items-center justify-center rounded-full ${specialtyMeta.colorClass}`
                    : event.type === "consultation"
                      ? "flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent/15 text-accent-light"
                      : event.type === "lab_order"
                        ? "flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-success/15 text-success"
                        : event.type === "imaging_order"
                          ? "flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-success/15 text-success"
                          : event.type === "hospitalization"
                            ? "flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent2/15 text-accent2-light"
                            : event.type === "surgical_procedure"
                              ? "flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent2/15 text-accent2-light"
                              : "flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-surface-hover text-text-subtle"
                }
              >
                {SpecialtyIcon ? (
                  <SpecialtyIcon size={14} />
                ) : event.type === "consultation" ? (
                  <Stethoscope size={14} />
                ) : event.type === "lab_order" ? (
                  <FlaskConical size={14} />
                ) : event.type === "imaging_order" ? (
                  <Scan size={14} />
                ) : event.type === "hospitalization" ? (
                  <BedDouble size={14} />
                ) : event.type === "surgical_procedure" ? (
                  <Syringe size={14} />
                ) : (
                  <History size={14} />
                )}
              </div>
              {index < events.length - 1 && <div className="mt-1 w-px flex-1 bg-border" />}
            </div>
            <div className="min-w-0 flex-1 pb-4">
              <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-2">
                  <p className="text-sm font-medium text-text">{event.summary}</p>
                  {specialtyMeta && <Badge status="neutral" dot={false}>{specialtyMeta.label}</Badge>}
                </div>
                <span className="shrink-0 text-xs text-text-subtle">{formatDateTime(event.date)}</span>
              </div>
              {consultation && <ConsultationEventDetails consultation={consultation} />}
              {labOrder && <LabOrderEventDetails order={labOrder} />}
              {imagingOrder && <ImagingOrderEventDetails order={imagingOrder} />}
              {hospitalization && <HospitalizationEventDetails hospitalization={hospitalization} />}
              {surgicalProcedure && <SurgicalProcedureEventDetails procedure={surgicalProcedure} />}
            </div>
          </li>
        );
      })}
    </ol>
  );
}

function ConsultationEventDetails({ consultation }: { consultation: Consultation }) {
  if (consultation.diagnoses.length === 0) return null;
  return (
    <div className="mt-1.5 flex flex-wrap gap-1.5">
      {consultation.diagnoses.map((d) => (
        <Badge key={d.id} status={d.type === "principal" ? "accent2" : "neutral"}>
          {d.code} — {d.label}
        </Badge>
      ))}
    </div>
  );
}

function LabOrderEventDetails({ order }: { order: LabOrder }) {
  if (order.items.length === 0) return null;
  return (
    <div className="mt-1.5 flex flex-wrap gap-1.5">
      {order.items.map((item) => (
        <Badge key={item.id} status="neutral" dot={false}>
          {item.loinc_code?.label ?? "Analyse"}
          {item.result?.value ? ` : ${item.result.value}${item.result.unit ? ` ${item.result.unit}` : ""}` : ""}
        </Badge>
      ))}
    </div>
  );
}

function HospitalizationEventDetails({ hospitalization }: { hospitalization: Hospitalization }) {
  return (
    <div className="mt-1.5 flex flex-wrap gap-1.5">
      <Badge status="neutral" dot={false}>
        {hospitalization.ward?.name ?? "—"}
      </Badge>
      <Badge status="neutral" dot={false}>
        Admis le {formatDate(hospitalization.admitted_at)}
        {hospitalization.discharged_at ? ` → sorti le ${formatDate(hospitalization.discharged_at)}` : ""}
      </Badge>
    </div>
  );
}

function SurgicalProcedureEventDetails({ procedure }: { procedure: SurgicalProcedure }) {
  return (
    <div className="mt-1.5 flex flex-wrap gap-1.5">
      <Badge status="neutral" dot={false}>
        {procedure.procedure_type}
      </Badge>
      <Badge status="neutral" dot={false}>
        Salle {procedure.operating_room}
      </Badge>
    </div>
  );
}

function ImagingOrderEventDetails({ order }: { order: ImagingOrder }) {
  const reports = order.studies.map((study) => study.report).filter((report) => report !== null);
  return (
    <div className="mt-1.5 space-y-1.5">
      <Badge status="neutral" dot={false}>
        {EXAM_TYPE_LABEL[order.exam_type]}
      </Badge>
      {reports.map((report) => (
        <p key={report!.id} className="whitespace-pre-wrap text-xs text-text-muted">
          {report!.content}
        </p>
      ))}
    </div>
  );
}
