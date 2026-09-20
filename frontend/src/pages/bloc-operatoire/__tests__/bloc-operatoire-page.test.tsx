import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { BlocOperatoirePage } from "@/pages/bloc-operatoire/bloc-operatoire-page";
import { api } from "@/lib/api";
import type { Patient } from "@/types/api";

vi.mock("@/lib/api", () => ({
  api: { get: vi.fn(), post: vi.fn() },
}));

// hasPermission désactivée : ce test cible uniquement le filtre patient de la
// liste (recherche par nom), pas la planification (déjà couverte par
// plan-surgical-procedure-dialog.test.tsx).
vi.mock("@/hooks/use-auth", () => ({
  useAuth: () => ({ user: { id: 1, sites: [{ id: 5, name: "PRINCIPALE" }] }, hasPermission: () => false }),
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

function renderPage() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <BlocOperatoirePage />
    </QueryClientProvider>,
  );
}

/**
 * Régression pour le bug report bloc opératoire : le filtre patient de la
 * liste des interventions doit résoudre le patient par recherche de nom
 * (PatientPicker), et transmettre son id résolu (`patient_id`) à
 * GET /surgical-procedures — jamais une saisie d'ID brute.
 */
describe("BlocOperatoirePage — filtre patient par nom", () => {
  beforeEach(() => {
    mockedGet.mockReset();
    mockedGet.mockImplementation(async (url, config) => {
      if (url === "/patients") return { data: { data: [patient()] } };
      if (url === "/surgical-procedures") return { data: { data: [] } };
      throw new Error(`Unexpected GET ${url} ${JSON.stringify(config?.params)}`);
    });
  });

  it("filters the procedures list by a patient found by name", async () => {
    const user = userEvent.setup();
    renderPage();

    await user.type(screen.getByPlaceholderText("Filtrer par patient..."), "Ndiaye");
    await waitFor(() => expect(screen.getByText("Awa Ndiaye")).toBeInTheDocument());
    await user.click(screen.getByText("Awa Ndiaye"));

    await waitFor(() =>
      expect(mockedGet).toHaveBeenCalledWith(
        "/surgical-procedures",
        expect.objectContaining({ params: expect.objectContaining({ patient_id: 11 }) }),
      ),
    );
  });
});
