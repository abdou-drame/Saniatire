import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { ConsultationForm } from "@/components/clinical/consultation-form";
import { api } from "@/lib/api";
import type { Consultation } from "@/types/api";

vi.mock("@/lib/api", () => ({
  api: { get: vi.fn(), post: vi.fn(), patch: vi.fn() },
}));

// Aucune des permissions cliniques annexes (IA, hospitalisation, bloc
// opératoire) n'est nécessaire à ces tests, et les désactiver évite de
// devoir aussi simuler leurs appels réseau propres.
vi.mock("@/hooks/use-auth", () => ({
  useAuth: () => ({ hasPermission: () => false, hasRole: () => false }),
}));

const mockedGet = vi.mocked(api.get);
const mockedPost = vi.mocked(api.post);

function existingConsultation(siteId: number): Consultation {
  return {
    id: 42,
    structure_id: 10,
    patient_id: 11,
    practitioner_id: 63,
    site_id: siteId,
    appointment_id: null,
    specialty_type: null,
    specialty: null,
    reason: "Douleur thoracique",
    history_of_illness: null,
    vitals: {
      weight_kg: null,
      height_cm: null,
      bmi: null,
      temperature_c: null,
      blood_pressure_systolic: null,
      blood_pressure_diastolic: null,
      heart_rate: null,
      respiratory_rate: null,
      spo2: null,
      glycemia: null,
      pain_scale: null,
      extra: null,
    },
    clinical_exam: null,
    recommendations: null,
    referral: null,
    follow_up_suggested_at: null,
    status: "en_cours",
    closed_at: null,
    diagnoses: [],
    created_at: "2026-09-02T00:00:00Z",
    updated_at: "2026-09-02T00:00:00Z",
  };
}

function renderForm(ui: React.ReactElement) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

// Ni l'hospitalisation ni le bloc opératoire ne sont affichés (permissions
// désactivées ci-dessus), mais leurs requêtes partent quand même au montage
// du formulaire — il faut les répondre pour ne pas planter le test.
function mockBackgroundQueries() {
  mockedGet.mockImplementation(async (url: string) => {
    if (url === "/hospitalizations") return { data: { data: [] } };
    if (url === "/surgical-procedures") return { data: { data: [] } };
    throw new Error(`Unexpected GET ${url}`);
  });
}

describe("ConsultationForm — association du site", () => {
  beforeEach(() => {
    mockedGet.mockReset();
    mockedPost.mockReset();
    mockBackgroundQueries();
  });

  /**
   * Régression pour le bug "consultation sans site associé, bloque la
   * prescription d'examens" : au moment de la création, le site provient de
   * la file d'attente/du rendez-vous d'origine (queueEntry.site_id, résolu
   * dans patient-detail-page.tsx et vérifié en base — les consultations #9
   * et #10 issues de ce flux ont bien reçu le site du rendez-vous, id 5).
   */
  it("creates a consultation from an existing appointment/queue entry using that appointment's site", async () => {
    mockedPost.mockResolvedValue({ data: { data: existingConsultation(5) } });

    const user = userEvent.setup();
    renderForm(<ConsultationForm patientId={11} practitionerId={63} siteId={5} appointmentId={7} />);

    await user.type(
      screen.getByPlaceholderText("Ex. douleur thoracique depuis 2 jours"),
      "Douleur thoracique",
    );
    await user.click(screen.getByRole("button", { name: "Créer la consultation" }));

    await waitFor(() =>
      expect(mockedPost).toHaveBeenCalledWith(
        "/consultations",
        expect.objectContaining({ site_id: 5, patient_id: 11, practitioner_id: 63, appointment_id: 7 }),
      ),
    );
  });

  /**
   * Sans rendez-vous préalable (démarrage direct depuis la fiche patient),
   * patient-detail-page.tsx retombe sur user.sites[0]?.id — le site actif
   * du praticien connecté — plutôt que sur un site de file d'attente
   * inexistant.
   */
  it("creates a consultation started without a prior appointment using the practitioner's own active site", async () => {
    mockedPost.mockResolvedValue({ data: { data: existingConsultation(9) } });

    const user = userEvent.setup();
    renderForm(<ConsultationForm patientId={11} practitionerId={63} siteId={9} appointmentId={null} />);

    await user.type(
      screen.getByPlaceholderText("Ex. douleur thoracique depuis 2 jours"),
      "Douleur thoracique",
    );
    await user.click(screen.getByRole("button", { name: "Créer la consultation" }));

    await waitFor(() =>
      expect(mockedPost).toHaveBeenCalledWith(
        "/consultations",
        expect.objectContaining({ site_id: 9, patient_id: 11, practitioner_id: 63, appointment_id: null }),
      ),
    );
  });

  /**
   * Régression directe pour le bug rapporté : une fois la consultation
   * créée, son site_id (garanti non nul côté backend) doit être utilisé
   * pour prescrire, même si le `siteId` ambiant (queueEntry/site de
   * l'utilisateur) est redevenu indisponible — ex. rechargement de page
   * ayant perdu le location.state du queueEntry, reproduit en conditions
   * réelles avec le compte medecin@teranga.test qui n'a aucun site
   * rattaché. Avant le correctif, le formulaire passait ce `siteId` (null)
   * tel quel aux dialogues de prescription au lieu du site réel de la
   * consultation, déclenchant "Aucun site associé à cette consultation".
   */
  it("still allows prescribing an imaging exam once the consultation's own site is used, even if the ambient siteId is unavailable", async () => {
    mockedPost.mockResolvedValue({ data: { data: { id: 100 } } });

    const user = userEvent.setup();
    renderForm(
      <ConsultationForm
        patientId={11}
        practitionerId={63}
        siteId={null}
        existingConsultation={existingConsultation(9)}
      />,
    );

    await user.click(screen.getByRole("button", { name: "Prescrire un examen d'imagerie" }));

    expect(screen.queryByText("Aucun site associé à cette consultation — impossible de prescrire.")).not.toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Créer la demande" }));

    await waitFor(() =>
      expect(mockedPost).toHaveBeenCalledWith(
        "/imaging-orders",
        expect.objectContaining({ site_id: 9, patient_id: 11, prescriber_id: 63, consultation_id: 42 }),
      ),
    );
  });
});
