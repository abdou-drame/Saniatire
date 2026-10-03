import { Outlet, useLocation, useNavigate } from "react-router-dom";
import { BRAND_NAME } from "@/components/brand/brand-logo";
import { Sidebar } from "@/components/layout/sidebar";
import { Topbar } from "@/components/layout/topbar";
import { navigationSections } from "@/config/navigation";
import { roleLabel } from "@/config/role-labels";
import { useAuth } from "@/hooks/use-auth";
import { usePatientSearch } from "@/hooks/use-patient-search";

function currentPageTitle(pathname: string): string {
  if (/^\/patients\/\d+/.test(pathname)) return "Dossier patient";
  for (const section of navigationSections) {
    const match = section.items.find((item) => item.href === pathname);
    if (match) return match.label;
  }
  return BRAND_NAME;
}

export function AppLayout() {
  const location = useLocation();
  const navigate = useNavigate();
  const { user, logout } = useAuth();
  const search = usePatientSearch();

  function handleLogout() {
    logout();
    navigate("/login", { replace: true });
  }

  return (
    <div className="flex h-screen bg-bg">
      <Sidebar sections={navigationSections} />
      <div className="flex min-w-0 flex-1 flex-col">
        <Topbar
          title={currentPageTitle(location.pathname)}
          notificationCount={0}
          userName={user ? `${user.first_name} ${user.last_name}` : "Utilisateur"}
          userRole={roleLabel(user?.roles[0])}
          onLogout={handleLogout}
          searchQuery={search.query}
          onSearchQueryChange={search.setQuery}
          searchResults={search.results}
          isSearching={search.isSearching}
          isSearchOpen={search.isOpen}
          searchTooShort={search.tooShort}
          onSelectPatient={(patient) => {
            search.setQuery("");
            navigate(`/patients/${patient.id}`);
          }}
        />
        {/* Texte et visibilité décidés par le backend (/auth/me) : la
            lecture seule elle-même est appliquée côté serveur (423). */}
        {user?.subscription?.alert && user.subscription.message && (
          <div
            role="alert"
            className={
              user.subscription.read_only
                ? "border-b border-danger/30 bg-danger/10 px-6 py-2 text-sm text-danger"
                : "border-b border-warning/30 bg-warning/10 px-6 py-2 text-sm text-warning"
            }
          >
            {user.subscription.message}
          </div>
        )}
        <main className="flex-1 overflow-y-auto p-6">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
