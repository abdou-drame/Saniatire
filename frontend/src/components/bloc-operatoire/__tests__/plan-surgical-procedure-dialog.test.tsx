import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { PlanSurgicalProcedureDialog } from "@/components/bloc-operatoire/plan-surgical-procedure-dialog";
import { api } from "@/lib/api";
import type { Patient, Practitioner, SurgicalProcedure } from "@/types/api";

vi.mock("@/lib/api", () => ({
  api: { get: vi.fn(), post: vi.fn() },
}));

// Un seul site personnel : useSiteSelection l'auto-résout silencieusement,
// sans afficher SiteSelectField — ce test se concentre sur la sélection par
// nom (Problème 2), pas sur la résolution du site (Problème 1, déjà couvert
// par AuthTest et Step3BlocOperatoireTest côté backend).
vi.mock("@/hooks/use-auth", () => ({
  useAuth: () => ({ user: { id: 1, sites: [{ id: 5, name: "PRINCIPALE" }] } }),
}));

const mockedGet = vi.mocked(api.get);
const mockedPost = vi.mocked(api.post);

function patient(): Patient {
  return {
    id: 11,
    structure_id: 10,
    patient_number: "P-0011",
    first_name: "Awa",
    last_name: "Ndiaye",
  } as Patient;
}

function practitioner(id: number, firstName: string, lastName: string): Practitioner {
  return { id, first_name: firstName, last_name: lastName };
}

function renderDialog() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <PlanSurgicalProcedureDialog open onOpenChange={() => {}} />
    </QueryClientProvider>,
  );
}

/**
 * Régression pour le bug report bloc opératoire (Problème 2) : le
 * formulaire demandait patient/chirurgien/anesthésiste par ID numérique
 * brut. Ce test vérifie que la sélection se fait désormais entièrement par
 * nom — recherche patient (PatientPicker) et listes déroulantes de
 * praticiens filtrées par rôle (/practitioners?role=...) — et que ce sont
 * bien les IDs résolus par ces recherches qui partent dans la requête, pas
 * une saisie manuelle.
 */
describe("PlanSurgicalProcedureDialog — sélection par nom", () => {
  beforeEach(() => {
    mockedGet.mockReset();
    mockedPost.mockReset();
    mockedGet.mockImplementation(async (url, config) => {
      const params = config?.params as Record<string, unknown> | undefined;
      if (url === "/patients") return { data: { data: [patient()] } };
      if (url === "/practitioners" && params?.role === "chirurgien") {
        return { data: { data: [practitioner(21, "Moussa", "Diop")] } };
      }
      if (url === "/practitioners" && params?.role === "anesthesiste") {
        return { data: { data: [practitioner(31, "Fatou", "Sarr")] } };
      }
      throw new Error(`Unexpected GET ${url} ${JSON.stringify(params)}`);
    });
  });

  it("plans a procedure using a patient found by name and practitioners chosen from role-filtered lists", async () => {
    const planned = { id: 99, status: "planifiee" } as SurgicalProcedure;
    mockedPost.mockResolvedValue({ data: { data: planned } });

    const user = userEvent.setup();
    renderDialog();

    await user.type(screen.getByPlaceholderText("Rechercher un patient (nom, n° dossier)..."), "Ndiaye");
    await waitFor(() => expect(screen.getByText("Awa Ndiaye")).toBeInTheDocument());
    await user.click(screen.getByText("Awa Ndiaye"));

    const [surgeonSelect, anesthesiologistSelect] = screen.getAllByRole("combobox");
    await waitFor(() => expect(screen.getByRole("option", { name: "Moussa Diop" })).toBeInTheDocument());
    await user.selectOptions(surgeonSelect, "21");
    await user.selectOptions(anesthesiologistSelect, "31");

    await user.type(screen.getByPlaceholderText("ex. Bloc 2"), "Bloc 3");
    await user.type(screen.getByPlaceholderText("ex. Appendicectomie"), "Cholécystectomie");

    await user.click(screen.getByRole("button", { name: "Planifier" }));

    await waitFor(() =>
      expect(mockedPost).toHaveBeenCalledWith(
        "/surgical-procedures",
        expect.objectContaining({
          site_id: 5,
          patient_id: 11,
          surgeon_id: 21,
          anesthesiologist_id: 31,
          operating_room: "Bloc 3",
          procedure_type: "Cholécystectomie",
        }),
      ),
    );
  });
});
