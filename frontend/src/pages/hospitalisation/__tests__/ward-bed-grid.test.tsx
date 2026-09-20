import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { WardBedGrid } from "@/pages/hospitalisation/ward-bed-grid";
import { api } from "@/lib/api";
import type { Ward } from "@/types/api";

vi.mock("@/lib/api", () => ({
  api: { get: vi.fn() },
}));

const mockedGet = vi.mocked(api.get);

const WARD: Ward = {
  id: 8,
  structure_id: 10,
  site_id: 5,
  name: "Materniter",
  beds: [
    {
      id: 33,
      structure_id: 10,
      site_id: 5,
      ward_id: 8,
      room_number: "200",
      bed_label: "B",
      status: "libre",
      created_at: "2026-01-01T00:00:00Z",
      updated_at: "2026-01-01T00:00:00Z",
    },
    {
      id: 34,
      structure_id: 10,
      site_id: 5,
      ward_id: 8,
      room_number: "204",
      bed_label: "C",
      status: "occupe",
      created_at: "2026-01-01T00:00:00Z",
      updated_at: "2026-01-01T00:00:00Z",
    },
  ],
  created_at: "2026-01-01T00:00:00Z",
  updated_at: "2026-01-01T00:00:00Z",
};

function renderWithClient(ui: React.ReactElement) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(<QueryClientProvider client={queryClient}>{ui}</QueryClientProvider>);
}

/**
 * Regression coverage for "le clic sur une tuile de lit libre ne fait rien
 * pour un infirmier" : la cause n'est pas un handler cassé mais l'absence
 * volontaire de `hospitalisation.create` chez ce rôle (voir
 * RolePermissionSeeder). HospitalisationPage ne transmet `onSelectFreeBed`
 * que si `hasPermission("hospitalisation.create")` — ce test verrouille le
 * comportement attendu pour chaque cas plutôt que de laisser le clic
 * échouer silencieusement sans indication.
 */
describe("WardBedGrid — interactivité des lits libres selon le rôle", () => {
  beforeEach(() => {
    mockedGet.mockReset();
    mockedGet.mockImplementation(async (url: string) => {
      if (url === "/wards") return { data: { data: [{ id: WARD.id }] } };
      if (url === `/wards/${WARD.id}`) return { data: { data: WARD } };
      throw new Error(`Unexpected GET ${url}`);
    });
  });

  it("un rôle avec hospitalisation.create (ex. médecin/administrateur) peut cliquer un lit libre pour ouvrir l'admission", async () => {
    const onSelectFreeBed = vi.fn();
    const user = userEvent.setup();
    renderWithClient(<WardBedGrid onSelectFreeBed={onSelectFreeBed} />);

    const freeTile = await screen.findByText("200 · B");
    await user.click(freeTile);

    expect(onSelectFreeBed).toHaveBeenCalledWith(expect.objectContaining({ id: WARD.id }), expect.objectContaining({ id: 33, status: "libre" }));
  });

  it("un rôle sans hospitalisation.create (ex. infirmier) voit un lit libre non interactif, pas un échec silencieux", async () => {
    const user = userEvent.setup();
    renderWithClient(<WardBedGrid />);

    const freeTile = await screen.findByText("200 · B");
    const tile = freeTile.closest("div")!;

    // Ni curseur cliquable ni rôle bouton : l'affordance visuelle ne ment
    // pas sur ce qui est possible.
    expect(tile).not.toHaveAttribute("role", "button");
    expect(tile.className).not.toContain("cursor-pointer");
    // Un survol explique pourquoi, plutôt que de laisser deviner.
    expect(tile).toHaveAttribute("title", "Vous n'avez pas les droits pour admettre un patient.");

    await user.click(tile);
    // Aucun crash, aucun effet — comportement volontairement inerte.
  });

  it("un lit occupé n'est jamais cliquable même pour un rôle autorisé", async () => {
    const onSelectFreeBed = vi.fn();
    renderWithClient(<WardBedGrid onSelectFreeBed={onSelectFreeBed} />);

    const occupiedTile = await screen.findByText("204 · C");
    const tile = occupiedTile.closest("div")!;

    expect(tile).not.toHaveAttribute("role", "button");
    expect(tile.className).not.toContain("cursor-pointer");
  });
});
