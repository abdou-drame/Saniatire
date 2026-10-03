import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import type { ReactNode } from "react";
import { MemoryRouter } from "react-router-dom";
import { beforeAll, beforeEach, describe, expect, it, vi } from "vitest";
import { StructureActivityCard } from "@/components/platform/structure-activity-card";
import { StructureUsersCard } from "@/components/platform/structure-users-card";
import { platformApi } from "@/lib/platform-api";
import { PlatformStatsPage } from "@/pages/platform/platform-stats-page";
import type { Paginated, PlatformStats, PlatformStructureActivity, PlatformStructureUser } from "@/types/api";

vi.mock("@/lib/platform-api", () => ({
  platformApi: { get: vi.fn(), post: vi.fn() },
}));

const mockedGet = vi.mocked(platformApi.get);

beforeAll(() => {
  // jsdom n'implémente pas ResizeObserver, requis par ResponsiveContainer (recharts).
  globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
  } as unknown as typeof ResizeObserver;
});

function renderWithClient(ui: ReactNode) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter>{ui}</MemoryRouter>
    </QueryClientProvider>,
  );
}

const USERS_PAGE: Paginated<PlatformStructureUser> = {
  data: [
    {
      id: 7,
      first_name: "Awa",
      last_name: "Diallo",
      email: "awa@clinique.test",
      roles: ["custom-role-slug"],
      is_active: true,
      is_locked: true,
      locked_until: "2026-10-03T12:00:00Z",
      last_login_at: null,
    },
    {
      id: 8,
      first_name: "Moussa",
      last_name: "Ba",
      email: "moussa@clinique.test",
      roles: [],
      is_active: false,
      is_locked: false,
      locked_until: null,
      last_login_at: "2026-10-01T08:30:00Z",
    },
  ],
  meta: { current_page: 1, from: 1, last_page: 2, per_page: 2, to: 2, total: 3 },
  links: { first: null, last: null, prev: null, next: null },
};

function activity(days: number, actions: number): PlatformStructureActivity {
  return {
    period: { from: "2026-09-04", to: "2026-10-03", days },
    last_login_at: null,
    users_total: 12,
    users_active: 10,
    users_logged_in_period: days === 7 ? 3 : 8,
    actions_total: actions,
    actions_per_day: [
      { date: "2026-10-02", count: 4 },
      { date: "2026-10-03", count: 6 },
    ],
  };
}

const STATS: PlatformStats = {
  months: ["2026-05", "2026-06", "2026-07", "2026-08", "2026-09", "2026-10"],
  totals: {
    structures: { total: 5, active: 3, inactive: 1, archived: 1 },
    users_active_30d: 42,
    patients: 1250,
    consultations_this_month: 87,
  },
  structures: [
    {
      id: 3,
      legal_name: "Clinique du Fleuve",
      is_active: true,
      is_archived: false,
      users_active_30d: 18,
      patients: 640,
      consultations_per_month: [
        { month: "2026-05", count: 11 },
        { month: "2026-10", count: 29 },
      ],
    },
    {
      id: 4,
      legal_name: "Cabinet Archivé",
      is_active: false,
      is_archived: true,
      users_active_30d: 0,
      patients: 20,
      consultations_per_month: [],
    },
  ],
};

describe("StructureUsersCard", () => {
  beforeEach(() => mockedGet.mockReset());

  it("liste les utilisateurs avec statut, verrouillage et dernière connexion", async () => {
    mockedGet.mockResolvedValue({ data: USERS_PAGE });
    renderWithClient(<StructureUsersCard structureId={3} />);

    expect(await screen.findByText("Awa Diallo")).toBeInTheDocument();
    expect(mockedGet).toHaveBeenCalledWith("/platform/structures/3/users", { params: { page: 1 } });
    expect(screen.getByText("Moussa Ba")).toBeInTheDocument();
    expect(screen.getByText("custom-role-slug")).toBeInTheDocument();
    expect(screen.getByText("Verrouillé")).toBeInTheDocument();
    expect(screen.getByText("Actif")).toBeInTheDocument();
    expect(screen.getByText("Désactivé")).toBeInTheDocument();
    expect(screen.getByText("Jamais")).toBeInTheDocument();
    expect(screen.getByText(/Page 1 \/ 2/)).toBeInTheDocument();
    // Lecture seule : aucune action de gestion sur cette carte.
    expect(screen.queryByRole("button", { name: "Débloquer" })).not.toBeInTheDocument();
  });

  it("charge la page suivante", async () => {
    mockedGet.mockResolvedValue({ data: USERS_PAGE });
    const user = userEvent.setup();
    renderWithClient(<StructureUsersCard structureId={3} />);

    await user.click(await screen.findByRole("button", { name: "Page suivante" }));
    await waitFor(() =>
      expect(mockedGet).toHaveBeenCalledWith("/platform/structures/3/users", { params: { page: 2 } }),
    );
  });
});

describe("StructureActivityCard", () => {
  beforeEach(() => mockedGet.mockReset());

  it("affiche les indicateurs puis recharge sur 7 jours", async () => {
    mockedGet.mockImplementation(async (_url, config) => {
      const days = (config?.params as { days?: number } | undefined)?.days ?? 30;
      return { data: { data: activity(days, days === 7 ? 15 : 120) } };
    });
    const user = userEvent.setup();
    renderWithClient(<StructureActivityCard structureId={3} />);

    expect(await screen.findByText("120")).toBeInTheDocument();
    expect(mockedGet).toHaveBeenCalledWith("/platform/structures/3/activity", { params: { days: 30 } });
    expect(screen.getByText("Jamais")).toBeInTheDocument();
    expect(screen.getByText("8 / 12")).toBeInTheDocument();
    expect(screen.getByText(/Chiffres uniquement/)).toBeInTheDocument();

    await user.selectOptions(screen.getByLabelText("Période"), "7");

    await waitFor(() =>
      expect(mockedGet).toHaveBeenCalledWith("/platform/structures/3/activity", { params: { days: 7 } }),
    );
    expect(await screen.findByText("15")).toBeInTheDocument();
    expect(screen.getByText("3 / 12")).toBeInTheDocument();
  });
});

describe("PlatformStatsPage", () => {
  beforeEach(() => mockedGet.mockReset());

  it("affiche les totaux et une colonne par mois", async () => {
    mockedGet.mockResolvedValue({ data: { data: STATS } });
    renderWithClient(<PlatformStatsPage />);

    // Deux tableaux (synthèse puis consultations par mois) : la structure
    // apparaît dans chacun.
    const [summaryLink, monthlyLink] = await screen.findAllByRole("link", { name: "Clinique du Fleuve" });
    expect(summaryLink).toHaveAttribute("href", "/platform/structures/3");
    expect(monthlyLink).toHaveAttribute("href", "/platform/structures/3");
    expect(mockedGet).toHaveBeenCalledWith("/platform/stats");

    expect(screen.getByText("42")).toBeInTheDocument();
    expect(screen.getByText("87")).toBeInTheDocument();
    const breakdown = screen.getByTestId("structures-breakdown");
    expect(within(breakdown).getByText("3 actives")).toBeInTheDocument();
    expect(within(breakdown).getByText("1 inactive")).toBeInTheDocument();
    expect(within(breakdown).getByText("1 archivée")).toBeInTheDocument();

    const headers = screen.getAllByRole("columnheader").map((th) => th.textContent);
    expect(headers).toContain("mai 26");
    expect(headers).toContain("oct. 26");
    expect(headers.filter((h) => /\b26$/.test(h ?? ""))).toHaveLength(6);

    // Synthèse : statut, actifs 30 j, patients, consultations du mois en cours.
    const summaryRow = summaryLink.closest("tr")!;
    expect(within(summaryRow).getByText("Active")).toBeInTheDocument();
    expect(within(summaryRow).getByText("18")).toBeInTheDocument();
    expect(within(summaryRow).getByText("640")).toBeInTheDocument();
    expect(within(summaryRow).getByText("29")).toBeInTheDocument();

    // Mensuel : mois manquants à 0, mai et octobre renseignés.
    const monthlyRow = monthlyLink.closest("tr")!;
    expect(within(monthlyRow).getByText("11")).toBeInTheDocument();
    expect(within(monthlyRow).getByText("29")).toBeInTheDocument();
    expect(within(monthlyRow).getAllByText("0")).toHaveLength(4);

    const archivedRow = screen.getAllByRole("link", { name: "Cabinet Archivé" })[0].closest("tr")!;
    expect(within(archivedRow).getByText("Archivée")).toBeInTheDocument();
  });
});
