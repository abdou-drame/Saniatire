import { QueryClient, QueryClientProvider, useQuery } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { DiagnosisPicker } from "@/components/clinical/diagnosis-picker";
import { api } from "@/lib/api";
import type { ConsultationDiagnosis, IcdCode } from "@/types/api";

vi.mock("@/lib/api", () => ({
  api: { get: vi.fn(), post: vi.fn() },
}));

const mockedGet = vi.mocked(api.get);
const mockedPost = vi.mocked(api.post);

const SEARCH_RESULT: IcdCode = {
  id: 49,
  code: "5A10",
  version: "CIM-11",
  label: "Diabète sucré de type 1",
  parent_id: 48,
  level: "code",
  status: "actif",
};

const EXISTING_PRINCIPAL: ConsultationDiagnosis = {
  id: 1,
  consultation_id: 9,
  icd_code_id: 5,
  code: "BA00",
  label: "Hypertension essentielle",
  type: "principal",
  status: "provisoire",
  version: "CIM-11",
  created_at: "2026-01-01T00:00:00Z",
};

function renderWithClient(ui: React.ReactElement) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

async function searchAndClickResult(user: ReturnType<typeof userEvent.setup>) {
  const input = screen.getByPlaceholderText("Rechercher un code CIM (code ou libellé)...");
  await user.click(input);
  await user.type(input, "diab");

  const result = await screen.findByText("Diabète sucré de type 1", {}, { timeout: 2000 });
  await user.click(result);
}

describe("DiagnosisPicker", () => {
  beforeEach(() => {
    mockedGet.mockReset();
    mockedPost.mockReset();
  });

  /**
   * Regression test for the "clic sur un résultat CIM sans effet" bug:
   * DiagnosisPicker used to invalidate ["consultations", consultationId],
   * but the general consultation form actually feeds it via a different
   * query (useOpenConsultation's ["consultations", "open", patientId,
   * userId]) — so the POST succeeded but the list never refreshed. This
   * harness mirrors that real wiring (a parent query supplying `diagnoses`
   * as a prop) rather than passing a static array, so it would have caught
   * the mismatch.
   */
  it("adds a diagnosis to the form state after searching and clicking a CIM result, and posts the correct payload", async () => {
    let serverDiagnoses: ConsultationDiagnosis[] = [EXISTING_PRINCIPAL];

    mockedGet.mockImplementation(async (url: string) => {
      if (url === "/icd-codes") {
        return { data: { data: [SEARCH_RESULT] } };
      }
      if (url === "/consultations/open") {
        return { data: { data: serverDiagnoses } };
      }
      throw new Error(`Unexpected GET ${url}`);
    });

    mockedPost.mockImplementation(async (_url: string, data?: unknown) => {
      const body = data as { icd_code_id: number; type: string; status: string };
      const created: ConsultationDiagnosis = {
        id: 2,
        consultation_id: 9,
        icd_code_id: body.icd_code_id,
        code: SEARCH_RESULT.code,
        label: SEARCH_RESULT.label,
        version: SEARCH_RESULT.version,
        type: body.type as ConsultationDiagnosis["type"],
        status: body.status as ConsultationDiagnosis["status"],
        created_at: "2026-01-02T00:00:00Z",
      };
      serverDiagnoses = [...serverDiagnoses, created];
      return { data: { data: created } };
    });

    function Harness() {
      const { data } = useQuery({
        queryKey: ["consultations", "open", 11, 63],
        queryFn: async () => {
          const { data } = await api.get<{ data: ConsultationDiagnosis[] }>("/consultations/open");
          return data.data;
        },
        initialData: [EXISTING_PRINCIPAL],
      });
      return <DiagnosisPicker consultationId={9} diagnoses={data ?? []} />;
    }

    const user = userEvent.setup();
    renderWithClient(<Harness />);

    expect(screen.getByText("BA00")).toBeInTheDocument();

    await searchAndClickResult(user);

    expect(mockedPost).toHaveBeenCalledWith("/consultations/9/diagnoses", {
      icd_code_id: 49,
      type: "secondaire",
      status: "provisoire",
    });

    await waitFor(() => expect(screen.getByText("5A10")).toBeInTheDocument(), { timeout: 2000 });
    expect(screen.getByText("BA00")).toBeInTheDocument();
  });

  it("shows a visible error message when adding a diagnosis fails, instead of failing silently", async () => {
    mockedGet.mockImplementation(async (url: string) => {
      if (url === "/icd-codes") {
        return { data: { data: [SEARCH_RESULT] } };
      }
      throw new Error(`Unexpected GET ${url}`);
    });

    mockedPost.mockRejectedValue({
      isAxiosError: true,
      response: { status: 500, data: {} },
    });

    const user = userEvent.setup();
    renderWithClient(<DiagnosisPicker consultationId={9} diagnoses={[EXISTING_PRINCIPAL]} />);

    await searchAndClickResult(user);

    expect(
      await screen.findByText("Le serveur a rencontré une erreur. Réessayez dans un instant.", {}, { timeout: 2000 }),
    ).toBeInTheDocument();
  });
});
