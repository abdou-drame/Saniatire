import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen } from "@testing-library/react";
import { MemoryRouter } from "react-router-dom";
import { describe, expect, it, vi } from "vitest";
import App from "@/App";
import { api } from "@/lib/api";

vi.mock("@/lib/api", () => ({
  api: { get: vi.fn(), post: vi.fn() },
}));

function renderAt(path: string) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={queryClient}>
      <MemoryRouter initialEntries={[path]}>
        <App />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe("LandingPage", () => {
  it("s'affiche sur / sans authentification ni appel API", () => {
    renderAt("/");

    expect(screen.getByRole("heading", { level: 1 })).toHaveTextContent(
      "Toute la gestion de votre structure de santé",
    );
    expect(vi.mocked(api.get)).not.toHaveBeenCalled();
    expect(vi.mocked(api.post)).not.toHaveBeenCalled();
  });

  it("pointe les 4 boutons de connexion vers les routes existantes", () => {
    renderAt("/");

    expect(screen.getByRole("link", { name: /Connexion personnel/ })).toHaveAttribute("href", "/login");
    expect(screen.getByRole("link", { name: /Connexion patient/ })).toHaveAttribute("href", "/portail/login");
    expect(screen.getByRole("link", { name: /Connexion prescripteur/ })).toHaveAttribute(
      "href",
      "/portail-prescripteur/login",
    );
    expect(screen.getByRole("link", { name: /Connexion administrateur/ })).toHaveAttribute(
      "href",
      "/platform/login",
    );
  });
});
