import { useMemo, useState } from "react";
import { ArrowLeft, LoaderCircle, Send } from "lucide-react";
import { Link, useNavigate } from "react-router-dom";
import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Select } from "@/components/ui/select";
import { Textarea } from "@/components/ui/textarea";
import { usePortalAppointments } from "@/hooks/portal/use-portal-appointments";
import { useCreatePortalComplaint } from "@/hooks/portal/use-portal-complaints";
import { formatDate } from "@/lib/datetime";
import { portalErrorMessage } from "@/lib/portal-error";

/**
 * Le patient soumet toujours pour lui-même — aucun champ "quel patient",
 * contrairement à CreateComplaintDialog côté personnel : patient_id est
 * dérivé de $request->user() côté serveur (PatientPortalController::
 * storeComplaint), jamais envoyé depuis ce formulaire.
 */
export function PortalNewComplaintPage() {
  const navigate = useNavigate();
  const appointmentsQuery = usePortalAppointments();

  const [motif, setMotif] = useState("");
  const [description, setDescription] = useState("");
  const [serviceConcerne, setServiceConcerne] = useState("");
  const [selectedAppointmentId, setSelectedAppointmentId] = useState<number | "">("");
  const [notice, setNotice] = useState<string | null>(null);

  const createMutation = useCreatePortalComplaint();

  const recentAppointments = useMemo(
    () => [...(appointmentsQuery.data ?? [])].sort((a, b) => b.starts_at.localeCompare(a.starts_at)).slice(0, 20),
    [appointmentsQuery.data],
  );

  function handleAppointmentSelect(value: string) {
    if (!value) {
      setSelectedAppointmentId("");
      return;
    }
    const id = Number(value);
    setSelectedAppointmentId(id);
    const appointment = recentAppointments.find((a) => a.id === id);
    if (appointment) {
      setServiceConcerne(`Rendez-vous du ${formatDate(appointment.starts_at)}${appointment.reason ? ` (${appointment.reason})` : ""}`);
    }
  }

  function handleSubmit() {
    if (!motif.trim() || !description.trim()) return;
    setNotice(null);
    createMutation.mutate(
      { motif: motif.trim(), description: description.trim(), service_concerne: serviceConcerne.trim() || undefined },
      {
        onSuccess: (complaint) => navigate(`/portail/reclamations/${complaint.id}`, { replace: true }),
        onError: (error) => setNotice(portalErrorMessage(error)),
      },
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center gap-3">
        <Link to="/portail/reclamations" className="text-text-muted hover:text-text">
          <ArrowLeft size={18} />
        </Link>
        <h2 className="font-heading text-lg font-semibold text-text">Nouvelle réclamation</h2>
      </div>

      <Card>
        <CardContent className="space-y-5 pt-5">
          <div className="space-y-1.5">
            <label htmlFor="complaint-motif" className="text-xs font-medium text-text-muted">
              Motif
            </label>
            <input
              id="complaint-motif"
              value={motif}
              onChange={(e) => setMotif(e.target.value)}
              className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              placeholder="Ex : délai d'attente, accueil, facturation…"
            />
          </div>

          <div className="space-y-1.5">
            <label htmlFor="complaint-description" className="text-xs font-medium text-text-muted">
              Description
            </label>
            <Textarea
              id="complaint-description"
              rows={5}
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              placeholder="Décrivez ce qui s'est passé…"
            />
          </div>

          {recentAppointments.length > 0 && (
            <div className="space-y-1.5">
              <label className="text-xs font-medium text-text-muted">Rendez-vous concerné (facultatif)</label>
              <Select
                value={selectedAppointmentId}
                onChange={(e) => handleAppointmentSelect(e.target.value)}
              >
                <option value="">Aucun rendez-vous en particulier</option>
                {recentAppointments.map((appointment) => (
                  <option key={appointment.id} value={appointment.id}>
                    {formatDate(appointment.starts_at)}
                    {appointment.reason ? ` — ${appointment.reason}` : ""}
                  </option>
                ))}
              </Select>
            </div>
          )}

          <div className="space-y-1.5">
            <label htmlFor="complaint-service" className="text-xs font-medium text-text-muted">
              Prestation concernée (facultatif)
            </label>
            <input
              id="complaint-service"
              value={serviceConcerne}
              onChange={(e) => setServiceConcerne(e.target.value)}
              className="h-9 w-full rounded-md border border-border bg-bg px-3 text-sm text-text focus:border-border-strong focus:outline-none focus:ring-1 focus:ring-accent"
              placeholder="Ex : consultation du 12/03, service de radiologie…"
            />
          </div>

          {notice && (
            <p className="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">{notice}</p>
          )}

          <Button className="w-full" disabled={!motif.trim() || !description.trim() || createMutation.isPending} onClick={handleSubmit}>
            {createMutation.isPending ? <LoaderCircle size={16} className="animate-spin" /> : <Send size={16} />}
            Envoyer la réclamation
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
