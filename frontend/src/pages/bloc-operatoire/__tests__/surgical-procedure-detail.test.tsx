import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";
import { SurgicalProcedureDetail } from "@/pages/bloc-operatoire/surgical-procedure-detail";
import { api } from "@/lib/api";
import type { SurgicalProcedure } from "@/types/api";

vi.mock("@/lib/api", () => ({
  api: { get: vi.fn(), post: vi.fn() },
}));

vi.mock("@/hooks/use-auth", () => ({
  useAuth: () => ({ user: { id: 1, sites: [{ id: 5, name: "PRINCIPALE" }] }, hasPermission: () => true }),
}));

const mockedGet = vi.mocked(api.get);

function procedure(): SurgicalProcedure {
  return {
    id: 42,
    status: "en_cours",
    procedure_type: "Appendicectomie",
    operating_room: "Bloc 1",
    scheduled_at: "2026-09-01T08:00:00.000000Z",
    surgeon_label: "Dr Fatou Sarr",
    anesthesiologist_label: "Dr Moussa Ba",
    patient: { id: 11, first_name: "Awa", last_name: "Ndiaye" },
    checklists: [
      {
        step: "avant_anesthesie",
        validated_at: "2026-09-01T08:05:00.000000Z",
        validated_by: 7,
        validator_label: "Moussa Ba",
        validator_role: "anesthesiste",
      },
    ],
  } as unknown as SurgicalProcedure;
}

function renderDetail() {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <SurgicalProcedureDetail procedureId={42} onClose={() => {}} />
    </QueryClientProvider>,
  );
}

/**
 * Régression bug report QA : le nom (et le rôle) du validateur d'une étape
 * de checklist doit être affiché à l'écran, pas seulement renvoyé par
 * l'API — corollaire frontend de
 * Step3BlocOperatoireTest::test_the_checklist_validator_identity_is_returned_by_the_api.
 */
describe("SurgicalProcedureDetail — identité du validateur de la checklist", () => {
  it("displays the validator's name and role next to the validated step", async () => {
    mockedGet.mockResolvedValue({ data: { data: procedure() } });

    renderDetail();

    expect(await screen.findByText(/par Moussa Ba \(Anesthésiste\)/)).toBeInTheDocument();
  });
});
