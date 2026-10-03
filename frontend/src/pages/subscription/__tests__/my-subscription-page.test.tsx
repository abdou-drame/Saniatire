import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { MemoryRouter } from "react-router-dom";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { MySubscriptionPage } from "@/pages/subscription/my-subscription-page";
import { api } from "@/lib/api";
import type { MySubscription } from "@/types/api";

vi.mock("@/lib/api", () => ({
  api: { get: vi.fn(), post: vi.fn() },
}));

const mockedGet = vi.mocked(api.get);
const mockedPost = vi.mocked(api.post);

const DATA: MySubscription = {
  state: "essai_ou_actif",
  read_only: false,
  current: {
    plan_name: "SALIHA Pro",
    status: "active",
    billing_period: "annual",
    starts_at: "2026-01-01",
    ends_at: "2026-12-31",
    grace_ends_at: "2027-01-07",
  },
  upcoming: null,
  renewal: {
    plan_name: "SALIHA Pro",
    period: "annual",
    amount: 350000,
    currency: "XOF",
    starts_at: "2027-01-01",
    ends_at: "2027-12-31",
  },
  can_pay_online: true,
  unavailable_reason: null,
  payments: [],
};

function renderPage(data: MySubscription, url = "/mon-abonnement") {
  mockedGet.mockResolvedValue({ data: { data } });
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[url]}>
        <MySubscriptionPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe("MySubscriptionPage", () => {
  const assign = vi.fn();

  beforeEach(() => {
    mockedGet.mockReset();
    mockedPost.mockReset();
    assign.mockReset();
    vi.stubGlobal("location", { ...window.location, assign });
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("affiche formule, échéance, montant et moyens de paiement hors de toute urgence", async () => {
    renderPage(DATA);

    expect(await screen.findByText("31/12/2026")).toBeInTheDocument();
    expect(screen.getByText("À jour")).toBeInTheDocument();
    expect(screen.getAllByText(/SALIHA Pro/).length).toBeGreaterThan(0);
    expect(screen.getByText(/paiement sécurisé via dexpay/i)).toBeInTheDocument();
    expect(screen.getByLabelText("Wave")).toBeInTheDocument();
    expect(screen.getByLabelText("Orange Money")).toBeInTheDocument();
    expect(mockedGet).toHaveBeenCalledWith("/subscription");
  });

  it("permet de payer en avance et redirige vers DexPay", async () => {
    mockedPost.mockResolvedValue({ data: { data: { payment_url: "https://pay.dexpay.africa/checkout/SUB-1" } } });
    renderPage(DATA);

    await userEvent.click(await screen.findByRole("button", { name: /payer 350/i }));

    await waitFor(() => expect(assign).toHaveBeenCalledWith("https://pay.dexpay.africa/checkout/SUB-1"));
    expect(mockedPost).toHaveBeenCalledWith("/subscription/dexpay-checkout");
  });

  it("explique pourquoi le paiement en ligne est indisponible", async () => {
    renderPage({
      ...DATA,
      renewal: null,
      can_pay_online: false,
      unavailable_reason: "Votre formule (Enterprise) n'a pas de tarif en ligne : contactez Saliha Health.",
    });

    expect(await screen.findByText(/pas de tarif en ligne/i)).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /payer/i })).not.toBeInTheDocument();
  });

  it("affiche le retour de paiement", async () => {
    renderPage(DATA, "/mon-abonnement?paiement=succes");
    expect(await screen.findByText(/paiement transmis/i)).toBeInTheDocument();
  });
});
