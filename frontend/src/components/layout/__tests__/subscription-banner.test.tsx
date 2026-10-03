import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import { SubscriptionBanner } from "@/components/layout/subscription-banner";
import { api } from "@/lib/api";
import type { SubscriptionStatus } from "@/types/api";

vi.mock("@/lib/api", () => ({
  api: { post: vi.fn() },
}));

const mockedPost = vi.mocked(api.post);

const GRACE: SubscriptionStatus = {
  state: "en_grace",
  read_only: false,
  alert: true,
  message: "L'abonnement de votre structure a expiré le 30/09/2026.",
  plan_name: "Pro",
  status: "active",
  ends_at: "2026-09-30",
  grace_ends_at: "2026-10-07",
  can_pay_online: true,
};

function renderBanner(subscription: SubscriptionStatus | undefined) {
  const queryClient = new QueryClient({ defaultOptions: { mutations: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <SubscriptionBanner subscription={subscription} />
    </QueryClientProvider>,
  );
}

describe("SubscriptionBanner", () => {
  const assign = vi.fn();

  beforeEach(() => {
    mockedPost.mockReset();
    assign.mockReset();
    vi.stubGlobal("location", { ...window.location, assign });
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("n'affiche rien sans alerte", () => {
    const { container } = renderBanner({ ...GRACE, alert: false, message: null });
    expect(container).toBeEmptyDOMElement();
  });

  it("masque le bouton quand le backend ne propose pas le paiement en ligne", () => {
    renderBanner({ ...GRACE, can_pay_online: false });
    expect(screen.getByRole("alert")).toHaveTextContent("a expiré le 30/09/2026");
    expect(screen.queryByRole("button", { name: /payer maintenant/i })).not.toBeInTheDocument();
  });

  it("ouvre une session DexPay sans paramètre et redirige vers le lien", async () => {
    mockedPost.mockResolvedValue({ data: { data: { payment_url: "https://pay.dexpay.africa/checkout/SUB-1" } } });
    renderBanner(GRACE);

    await userEvent.click(screen.getByRole("button", { name: /payer maintenant/i }));

    await waitFor(() => expect(assign).toHaveBeenCalledWith("https://pay.dexpay.africa/checkout/SUB-1"));
    expect(mockedPost).toHaveBeenCalledWith("/subscription/dexpay-checkout");
  });

  it("affiche l'erreur du backend sans rediriger", async () => {
    mockedPost.mockRejectedValue({
      isAxiosError: true,
      response: { status: 422, data: { message: "Aucun abonnement n'est encore enregistré pour votre structure." } },
    });
    renderBanner(GRACE);

    await userEvent.click(screen.getByRole("button", { name: /payer maintenant/i }));

    expect(await screen.findByText(/aucun abonnement/i)).toBeInTheDocument();
    expect(assign).not.toHaveBeenCalled();
  });
});
