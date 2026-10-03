import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";
import { StructureAdministratorsCard } from "@/components/platform/structure-administrators-card";
import { platformApi } from "@/lib/platform-api";
import type { PlatformStaffUser } from "@/types/api";

vi.mock("@/lib/platform-api", () => ({
  platformApi: { get: vi.fn(), post: vi.fn() },
}));

const mockedGet = vi.mocked(platformApi.get);
const mockedPost = vi.mocked(platformApi.post);

const ADMIN: PlatformStaffUser = {
  id: 7,
  structure_id: 3,
  first_name: "Awa",
  last_name: "Diallo",
  email: "awa@clinique.test",
  is_active: true,
  must_change_password: true,
  is_locked: true,
  locked_until: "2026-10-03T12:00:00Z",
  failed_login_attempts: 5,
  created_at: "2026-01-01T00:00:00Z",
};

const CREATED: PlatformStaffUser = {
  ...ADMIN,
  id: 8,
  first_name: "Moussa",
  last_name: "Ba",
  email: "moussa@clinique.test",
  is_locked: false,
  locked_until: null,
  failed_login_attempts: 0,
};

function renderCard(readOnly = false) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <StructureAdministratorsCard structureId={3} readOnly={readOnly} />
    </QueryClientProvider>,
  );
}

describe("StructureAdministratorsCard", () => {
  beforeEach(() => {
    mockedGet.mockReset();
    mockedPost.mockReset();
  });

  it("liste les administrateurs avec leurs badges d'état", async () => {
    mockedGet.mockResolvedValue({ data: { data: [ADMIN] } });
    renderCard();

    expect(await screen.findByText("Awa Diallo")).toBeInTheDocument();
    expect(mockedGet).toHaveBeenCalledWith("/platform/structures/3/administrators");
    expect(screen.getByText("awa@clinique.test")).toBeInTheDocument();
    expect(screen.getByText("Actif")).toBeInTheDocument();
    expect(screen.getByText("Verrouillé")).toBeInTheDocument();
    expect(screen.getByText("Changement de mot de passe exigé")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Débloquer" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Désactiver" })).toBeInTheDocument();
  });

  it("n'offre aucune action quand la structure est archivée", async () => {
    mockedGet.mockResolvedValue({ data: { data: [ADMIN] } });
    renderCard(true);

    expect(await screen.findByText("Awa Diallo")).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: /Nouvel administrateur/ })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Débloquer" })).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Réinitialiser le mot de passe" })).not.toBeInTheDocument();
  });

  it("affiche le mot de passe généré une seule fois après la création", async () => {
    mockedGet.mockResolvedValue({ data: { data: [ADMIN] } });
    mockedPost.mockResolvedValue({
      data: { data: CREATED, generated_password: "Xy7-secret-42", message: "Administrateur créé." },
    });
    const user = userEvent.setup();
    renderCard();

    await user.click(await screen.findByRole("button", { name: /Nouvel administrateur/ }));
    await user.type(screen.getByLabelText("Prénom"), "Moussa");
    await user.type(screen.getByLabelText("Nom"), "Ba");
    await user.type(screen.getByLabelText("E-mail de connexion"), "moussa@clinique.test");
    await user.click(screen.getByRole("button", { name: "Créer l'administrateur" }));

    await waitFor(() =>
      expect(mockedPost).toHaveBeenCalledWith("/platform/structures/3/administrators", {
        first_name: "Moussa",
        last_name: "Ba",
        email: "moussa@clinique.test",
      }),
    );

    const dialog = await screen.findByRole("dialog");
    expect(within(dialog).getByText("Administrateur créé")).toBeInTheDocument();
    expect(within(dialog).getByTestId("generated-password")).toHaveTextContent("Xy7-secret-42");
    expect(within(dialog).getByText(/ne sera plus jamais consultable/)).toBeInTheDocument();
    expect(within(dialog).getByText(/changement de mot de passe sera exigé/)).toBeInTheDocument();

    await user.click(within(dialog).getByRole("button", { name: "J'ai noté le mot de passe" }));

    await waitFor(() => expect(screen.queryByRole("dialog")).not.toBeInTheDocument());
    expect(screen.queryByText("Xy7-secret-42")).not.toBeInTheDocument();
  });

  it("valide les champs en français avant tout appel", async () => {
    mockedGet.mockResolvedValue({ data: { data: [] } });
    const user = userEvent.setup();
    renderCard();

    await user.click(await screen.findByRole("button", { name: /Nouvel administrateur/ }));
    await user.click(screen.getByRole("button", { name: "Créer l'administrateur" }));

    expect(screen.getByText("Le prénom est obligatoire.")).toBeInTheDocument();
    expect(screen.getByText("Le nom est obligatoire.")).toBeInTheDocument();
    expect(screen.getByText("L'email est obligatoire.")).toBeInTheDocument();
    expect(mockedPost).not.toHaveBeenCalled();
  });
});
