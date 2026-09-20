import { Card } from "@/components/ui/card";
import { CashSessionWidget } from "@/components/dashboard/cash-session-widget";
import { ComplaintsWidget } from "@/components/dashboard/complaints-widget";
import { DirectionSummaryWidget } from "@/components/dashboard/direction-summary-widget";
import { EquipmentMaintenanceWidget } from "@/components/dashboard/equipment-maintenance-widget";
import { FinancialSummaryWidget } from "@/components/dashboard/financial-summary-widget";
import { LeaveRequestsWidget } from "@/components/dashboard/leave-requests-widget";
import { MedicalSummaryWidget } from "@/components/dashboard/medical-summary-widget";
import { OnCallWidget } from "@/components/dashboard/on-call-widget";
import { PurchaseOrdersWidget } from "@/components/dashboard/purchase-orders-widget";
import { QualiteSummaryWidget } from "@/components/dashboard/qualite-summary-widget";
import { QueueWidget } from "@/components/dashboard/queue-widget";
import { StockAlertsWidget } from "@/components/dashboard/stock-alerts-widget";
import { TodayAppointmentsWidget } from "@/components/dashboard/today-appointments-widget";
import { useAuth } from "@/hooks/use-auth";

/**
 * Grille de widgets indépendants, chacun gardé par la permission backend
 * exacte de son endpoint (jamais par nom de rôle) et rendu avec son propre
 * état loading/erreur — un widget en erreur n'affecte jamais les autres.
 * `OnCallWidget` n'a pas de garde : /on-call/now est ouvert à tout
 * utilisateur authentifié (urgence), donc la grille n'est jamais vide.
 */
export function DashboardPage() {
  const { user, hasPermission } = useAuth();

  return (
    <div className="space-y-6">
      <Card className="glow-accent relative overflow-hidden p-6">
        <div className="relative">
          <p className="text-sm text-text-muted">Bienvenue,</p>
          <h2 className="font-heading text-xl font-semibold text-text">
            {user ? `${user.first_name} ${user.last_name}` : "Utilisateur"}
          </h2>
          <p className="mt-1 text-sm text-text-muted">
            {user?.structure_name
              ? `Voici un aperçu de l'activité de ${user.structure_name}.`
              : "Voici un aperçu de l'activité de votre structure."}
          </p>
        </div>
      </Card>

      {hasPermission("dashboards.direction") && <DirectionSummaryWidget />}
      {hasPermission("dashboards.financier") && <FinancialSummaryWidget />}
      {hasPermission("dashboards.medical") && <MedicalSummaryWidget />}
      {hasPermission("dashboards.qualite") && <QualiteSummaryWidget />}

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {hasPermission("appointments.view") && <TodayAppointmentsWidget />}
        {hasPermission("queue.view") && <QueueWidget />}
        {hasPermission("caisse.view") && <CashSessionWidget />}
        {hasPermission("conges.validate") && <LeaveRequestsWidget />}
        <OnCallWidget />
        {hasPermission("stock.view") && <StockAlertsWidget />}
        {hasPermission("biomedical.view") && <EquipmentMaintenanceWidget />}
        {hasPermission("achats.view") && <PurchaseOrdersWidget />}
        {hasPermission("reclamations.manage_all") && <ComplaintsWidget />}
      </div>
    </div>
  );
}
