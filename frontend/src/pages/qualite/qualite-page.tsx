import { ComplaintsSection } from "@/pages/qualite/complaints-section";
import { QualiteOverviewSection } from "@/pages/qualite/qualite-overview-section";
import { SatisfactionSection } from "@/pages/qualite/satisfaction-section";
import { useAuth } from "@/hooks/use-auth";

/**
 * Écran Qualité et Réclamations, une seule page en sections `Card` empilées
 * (patron `conges-page.tsx`/`plannings-page.tsx`, pas d'onglets). Ordre
 * Vue d'ensemble → Satisfaction → Réclamations, reflet direct de la
 * numérotation 1→2→3 de la demande utilisateur.
 *
 * Aucune règle métier n'est recalculée ici : les chiffres de la Vue
 * d'ensemble viennent verbatim de GET /dashboards/qualite
 * (QualityDashboardService, seule source de vérité), et les transitions de
 * statut des réclamations restent arbitrées uniquement par le backend
 * (abort_if() de ComplaintController) — voir complaints-section.tsx.
 *
 * Vue d'ensemble et Satisfaction se gardent chacune par leur propre
 * permission (dashboards.qualite / qualite.view) ; Réclamations est
 * toujours rendue, son périmètre étant déjà assuré côté serveur par
 * ComplaintController::index (portée à l'utilisateur courant sans
 * reclamations.manage_all).
 */
export function QualitePage() {
  const { hasPermission } = useAuth();

  return (
    <div className="space-y-6">
      <div>
        <h1 className="font-heading text-xl font-semibold text-text">Qualité et Réclamations</h1>
        <p className="mt-1 text-sm text-text-muted">
          Satisfaction patient, réclamations et indicateurs qualité de la structure.
        </p>
      </div>

      {hasPermission("dashboards.qualite") && <QualiteOverviewSection />}
      {hasPermission("qualite.view") && <SatisfactionSection />}
      <ComplaintsSection />
    </div>
  );
}

/**
 * Pas de garde de route : aucun middleware de permission ne protège
 * index/show/respond/resolve/close côté backend (patron identique à
 * CongesRoute, pas à AchatsRoute) — un utilisateur sans aucun droit qualité
 * (ex. médecin) doit pouvoir répondre/résoudre/clôturer une réclamation qui
 * lui a été assignée, donc l'écran ne doit jamais être verrouillé au niveau
 * de la route.
 */
export function QualiteRoute() {
  return <QualitePage />;
}
