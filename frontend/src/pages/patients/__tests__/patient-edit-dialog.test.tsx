import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { PatientEditDialog } from "@/pages/patients/patient-edit-dialog";
import { api } from "@/lib/api";
import type { Patient } from "@/types/api";

vi.mock("@/lib/api", () => ({
  api: { patch: vi.fn() },
}));

const mockedPatch = vi.mocked(api.patch);

const PATIENT: Patient = {
  id: 11,
  structure_id: 10,
  patient_number: "PT-0010-2026-000003",
  first_name: "issa",
  last_name: "ndiaye",
  sex: "M",
  birth_date: "2025-12-30",
  phone: "5656555757",
  email: null,
  address: null,
  profession: null,
  nationality: null,
  emergency_contact_name: "Drame",
  emergency_contact_phone: "776543",
  emergency_contact_relationship: null,
  portal_activated_at: null,
};

function renderDialog(patient: Patient = PATIENT) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <PatientEditDialog open onOpenChange={() => {}} patient={patient} />
    </QueryClientProvider>,
  );
}

describe("PatientEditDialog", () => {
  beforeEach(() => {
    mockedPatch.mockReset();
  });

  /**
   * Regression test for "champ email manquant sur la fiche patient": no edit
   * form existed at all before this fix, so the email captured at intake
   * (if any) could never be added/corrected afterwards, and features that
   * depend on it (portal activation) would fail with no way to resolve it
   * from the UI.
   */
  it("shows an editable email field pre-filled from the patient, and saves the entered value", async () => {
    mockedPatch.mockResolvedValue({ data: { data: { ...PATIENT, email: "issa.ndiaye@example.com" } } });

    const user = userEvent.setup();
    renderDialog();

    const emailInput = screen.getByLabelText("Email") as HTMLInputElement;
    expect(emailInput.value).toBe("");

    await user.type(emailInput, "issa.ndiaye@example.com");
    await user.click(screen.getByRole("button", { name: "Enregistrer" }));

    await waitFor(() =>
      expect(mockedPatch).toHaveBeenCalledWith(
        "/patients/11",
        expect.objectContaining({ email: "issa.ndiaye@example.com" }),
      ),
    );
  });

  it("rejects a malformed email with a clear message and does not submit", async () => {
    const user = userEvent.setup();
    renderDialog();

    const emailInput = screen.getByLabelText("Email");
    await user.type(emailInput, "pas-un-email");
    await user.click(screen.getByRole("button", { name: "Enregistrer" }));

    expect(await screen.findByText("Adresse email invalide.")).toBeInTheDocument();
    expect(mockedPatch).not.toHaveBeenCalled();
  });
});
