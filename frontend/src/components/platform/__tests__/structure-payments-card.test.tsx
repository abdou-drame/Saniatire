import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { StructurePaymentsCard } from "@/components/platform/structure-payments-card";
import { platformApi } from "@/lib/platform-api";
import type { PaymentTransaction, Plan } from "@/types/api";

vi.mock("@/lib/platform-api", () => ({
  platformApi: { get: vi.fn(), post: vi.fn() },
}));

const mockedGet = vi.mocked(platformApi.get);
const mockedPost = vi.mocked(platformApi.post);

const PLANS = [
  { id: 2, code: "pro", name: "Pro", monthly_price_fcfa: 35000, annual_price_fcfa: 350000, is_active: true },
  { id: 5, code: "enterprise", name: "Enterprise", monthly_price_fcfa: null, annual_price_fcfa: null, is_active: true },
] as Plan[];

const TRANSACTION: PaymentTransaction = {
  id: 1,
  reference: "SUB-3-20261015100000-ABCDEF",
  plan_name: "Pro",
  period: "monthly",
  amount: 35000,
  currency: "XOF",
  status: "complete",
  provider: "dexpay",
  payment_url: "https://pay.dexpay.africa/checkout/SUB-3",
  origin: "structure_admin",
  subscription_id: 9,
  created_at: "2026-10-15T10:00:00Z",
  updated_at: "2026-10-15T10:05:00Z",
};

function renderCard(readOnly = false) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <StructurePaymentsCard structureId={3} readOnly={readOnly} />
    </QueryClientProvider>,
  );
}

describe("StructurePaymentsCard", () => {
  beforeEach(() => {
    mockedGet.mockReset();
    mockedPost.mockReset();
    mockedGet.mockImplementation(async (url: string) =>
      url === "/platform/plans" ? { data: { data: PLANS } } : { data: { data: [TRANSACTION] } },
    );
  });

  it("affiche l'historique des paiements avec statut et origine", async () => {
    renderCard();

    expect(await screen.findByText("SUB-3-20261015100000-ABCDEF", { exact: false })).toBeInTheDocument();
    expect(screen.getByText("Payé")).toBeInTheDocument();
    expect(screen.getByText(/payé par la structure/i)).toBeInTheDocument();
    expect(mockedGet).toHaveBeenCalledWith("/platform/structures/3/payment-transactions");
  });

  it("génère un lien sans envoyer de montant et l'affiche avec copie et ouverture", async () => {
    mockedPost.mockResolvedValue({ data: { data: { ...TRANSACTION, id: 2, status: "en_attente", origin: "platform_admin" } } });
    renderCard();

    await userEvent.click(await screen.findByRole("button", { name: /générer un lien de paiement dexpay/i }));
    // Enterprise (sur devis) n'est pas proposée.
    await waitFor(() => expect(screen.getByRole("option", { name: /pro/i })).toBeInTheDocument());
    expect(screen.queryByRole("option", { name: /enterprise/i })).not.toBeInTheDocument();

    await userEvent.selectOptions(screen.getByLabelText("Formule"), "2");
    await userEvent.click(screen.getByRole("button", { name: /générer le lien/i }));

    expect(await screen.findByText("https://pay.dexpay.africa/checkout/SUB-3")).toBeInTheDocument();
    expect(mockedPost).toHaveBeenCalledWith("/platform/structures/3/subscriptions/dexpay-checkout", {
      plan_id: 2,
      period: "monthly",
    });
    expect(screen.getByRole("button", { name: /copier le lien/i })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /ouvrir/i })).toBeInTheDocument();
  });

  async function generateLink() {
    await userEvent.click(await screen.findByRole("button", { name: /générer un lien de paiement dexpay/i }));
    await waitFor(() => expect(screen.getByRole("option", { name: /pro/i })).toBeInTheDocument());
    await userEvent.selectOptions(screen.getByLabelText("Formule"), "2");
    await userEvent.click(screen.getByRole("button", { name: /générer le lien/i }));
    await screen.findByText("https://pay.dexpay.africa/checkout/SUB-3");
  }

  it("envoie le lien par email à l'adresse de la structure", async () => {
    mockedPost.mockImplementation(async (url: string) =>
      url.endsWith("/send-email")
        ? { data: { data: { sent_to: "direction@clinique.sn" } } }
        : { data: { data: { ...TRANSACTION, id: 2, status: "en_attente", origin: "platform_admin" } } },
    );
    renderCard();
    await generateLink();

    await userEvent.click(screen.getByRole("button", { name: /envoyer par email/i }));

    expect(await screen.findByText("Lien envoyé à direction@clinique.sn.")).toBeInTheDocument();
    expect(mockedPost).toHaveBeenCalledWith("/platform/structures/3/payment-transactions/2/send-email");
    expect(screen.getByRole("button", { name: /envoyé/i })).toBeDisabled();
  });

  it("affiche l'erreur SMTP du serveur sans simuler de succès", async () => {
    const smtpMessage = "L'email n'a pas pu être envoyé (serveur d'envoi indisponible).";
    mockedPost.mockImplementation(async (url: string) => {
      if (url.endsWith("/send-email")) {
        throw { isAxiosError: true, response: { status: 503, data: { message: smtpMessage } } };
      }
      return { data: { data: { ...TRANSACTION, id: 2, status: "en_attente", origin: "platform_admin" } } };
    });
    renderCard();
    await generateLink();

    await userEvent.click(screen.getByRole("button", { name: /envoyer par email/i }));

    expect(await screen.findByRole("alert")).toHaveTextContent(smtpMessage);
    expect(screen.queryByText(/lien envoyé à/i)).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: /envoyer par email/i })).toBeEnabled();
  });

  it("masque la génération pour une structure archivée", async () => {
    renderCard(true);
    await screen.findByText("Payé");
    expect(screen.queryByRole("button", { name: /générer un lien/i })).not.toBeInTheDocument();
  });
});
