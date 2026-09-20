import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { LaboratoirePage } from "@/pages/laboratoire/laboratoire-page";
import { api } from "@/lib/api";
import type { Patient, Practitioner } from "@/types/api";

vi.mock("@/lib/api", () => ({
  api: { get: vi.fn(), post: vi.fn() },
}));

vi.mock("@/hooks/use-auth", () => ({
  useAuth: () => ({
    user: { id: 1, sites: [{ id: 5, name: "PRINCIPALE" }] },
    hasPermission: () => false,
    hasRole: () => false,
  }),
}));

const mockedGet = vi.mocked(api.get);

function patient(): Patient {
  return {
    id: 11,
    structure_id: 10,
    patient_number: "P-0011",
    first_name: "Awa",
    last_name: "Ndiaye",
  } as Patient;
}

function practitioner(): Practitioner {
  return { id: 41, first_name: "Moussa", last_name: "Diop" } as Practitioner;
}

const stats = {
  demandes_en_attente: 0,
  prelevements_du_jour: 0,
  resultats_en_attente_validation: 0,
  resultats_transmis_aujourdhui: 0,
};

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <LaboratoirePage />
    </QueryClientProvider>,
  );
}

/**
 * Régression pour le bug report bloc opératoire (même correctif appliqué aux
 * écrans de laboratoire) : les filtres patient et praticien prescripteur
 * doivent résoudre par nom (PatientPicker / liste de praticiens), pas par ID
 * brut.
 */
describe("LaboratoirePage — filtres par nom", () => {
  beforeEach(() => {
    mockedGet.mockReset();
    mockedGet.mockImplementation(async (url, config) => {
      if (url === "/lab-orders/stats") return { data: { data: stats } };
      if (url === "/practitioners") return { data: { data: [practitioner()] } };
      if (url === "/patients") return { data: { data: [patient()] } };
      if (url === "/lab-orders") return { data: { data: [] } };
      throw new Error(`Unexpected GET ${url} ${JSON.stringify(config?.params)}`);
    });
  });

  it("filters lab orders by a patient found by name and a requester chosen from the practitioner list", async () => {
    const user = userEvent.setup();
    renderPage();

    await user.type(screen.getByPlaceholderText("Filtrer par patient..."), "Ndiaye");
    await waitFor(() => expect(screen.getByText("Awa Ndiaye")).toBeInTheDocument());
    await user.click(screen.getByText("Awa Ndiaye"));

    await waitFor(() =>
      expect(mockedGet).toHaveBeenCalledWith(
        "/lab-orders",
        expect.objectContaining({ params: expect.objectContaining({ patient_id: 11 }) }),
      ),
    );

    await waitFor(() => expect(screen.getByRole("option", { name: "Moussa Diop" })).toBeInTheDocument());
    // Select natif sans association label/for : la grille de filtres rend
    // dans l'ordre [statut, patient (input), praticien], donc le 2e <select>
    // (index 1) est le filtre praticien prescripteur.
    const [, requesterSelect] = screen.getAllByRole("combobox");
    await user.selectOptions(requesterSelect, "41");

    await waitFor(() =>
      expect(mockedGet).toHaveBeenCalledWith(
        "/lab-orders",
        expect.objectContaining({ params: expect.objectContaining({ requester_id: 41 }) }),
      ),
    );
  });
});
