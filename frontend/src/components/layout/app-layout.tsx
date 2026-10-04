import { useCallback, useEffect, useRef, useState } from "react";
import { Outlet, useLocation, useNavigate } from "react-router-dom";
import { BRAND_NAME } from "@/components/brand/brand-logo";
import { Sidebar, SidebarDrawer } from "@/components/layout/sidebar";
import { SubscriptionBanner } from "@/components/layout/subscription-banner";
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
  const { user, logout, hasModule } = useAuth();
  const search = usePatientSearch();
  const [drawerOpen, setDrawerOpen] = useState(false);
  const menuButtonRef = useRef<HTMLButtonElement>(null);
  const wasOpen = useRef(false);

  // Livraison B : entrées des modules coupés masquées (la liste vient de
  // /auth/me, le backend refuse de toute façon leurs routes).
  const sections = navigationSections
    .map((section) => ({
      ...section,
      items: section.items.filter(
        (item) =>
          (!item.modules || item.modules.some(hasModule)) &&
          (!item.roles || item.roles.some((role) => user?.roles.includes(role))),
      ),
    }))
    .filter((section) => section.items.length > 0);

  const closeDrawer = useCallback(() => setDrawerOpen(false), []);

  // Changement de page (lien du tiroir, recherche patient…) : tiroir fermé.
  useEffect(() => {
    setDrawerOpen(false);
  }, [location.pathname]);

  // À la fermeture du tiroir, le focus revient au bouton menu.
  useEffect(() => {
    if (drawerOpen) {
      wasOpen.current = true;
    } else if (wasOpen.current) {
      wasOpen.current = false;
      menuButtonRef.current?.focus();
    }
  }, [drawerOpen]);

  function handleLogout() {
    setDrawerOpen(false);
    logout();
    navigate("/login", { replace: true });
  }

  return (
    <div className="flex h-screen bg-bg">
      <Sidebar sections={sections} />
      <SidebarDrawer sections={sections} open={drawerOpen} onClose={closeDrawer} />
      <div className="flex min-w-0 flex-1 flex-col">
        <Topbar
          title={currentPageTitle(location.pathname)}
          notificationCount={0}
          userName={user ? `${user.first_name} ${user.last_name}` : "Utilisateur"}
          userRole={roleLabel(user?.roles[0])}
          onLogout={handleLogout}
          menuButtonRef={menuButtonRef}
          isMenuOpen={drawerOpen}
          onOpenMenu={() => setDrawerOpen(true)}
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
        <SubscriptionBanner subscription={user?.subscription} />
        <main className="min-w-0 flex-1 overflow-y-auto p-4 sm:p-6">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
