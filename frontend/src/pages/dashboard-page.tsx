import { Activity, BedDouble, CalendarClock, Receipt, Users } from "lucide-react";
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import { Badge } from "@/components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DataTable, type DataTableColumn } from "@/components/ui/data-table";
import { EmptyState } from "@/components/ui/empty-state";
import { KpiCard } from "@/components/ui/kpi-card";
import { useAuth } from "@/hooks/use-auth";

const consultationsTrend = [
  { jour: "12/08", total: 34 },
  { jour: "13/08", total: 41 },
  { jour: "14/08", total: 38 },
  { jour: "15/08", total: 52 },
  { jour: "16/08", total: 47 },
  { jour: "17/08", total: 29 },
  { jour: "18/08", total: 31 },
  { jour: "19/08", total: 44 },
  { jour: "20/08", total: 49 },
  { jour: "21/08", total: 55 },
  { jour: "22/08", total: 50 },
];

type ActivityStatus = "termine" | "en_cours" | "en_retard" | "annule";

interface ActivityRow {
  id: number;
  patient: string;
  type: string;
  praticien: string;
  heure: string;
  statut: ActivityStatus;
}

const recentActivity: ActivityRow[] = [
  { id: 1, patient: "Aïssatou Ndiaye", type: "Consultation générale", praticien: "Dr. Fall", heure: "09:15", statut: "termine" },
  { id: 2, patient: "Moussa Ba", type: "Suivi post-opératoire", praticien: "Dr. Sarr", heure: "09:40", statut: "en_cours" },
  { id: 3, patient: "Fatou Diop", type: "Bilan sanguin", praticien: "Dr. Cissé", heure: "10:05", statut: "termine" },
  { id: 4, patient: "Ibrahima Sow", type: "Consultation cardiologie", praticien: "Dr. Fall", heure: "10:30", statut: "en_retard" },
  { id: 5, patient: "Mariama Diallo", type: "Contrôle diabète", praticien: "Dr. Sy", heure: "11:00", statut: "annule" },
  { id: 6, patient: "Ousmane Kane", type: "Consultation pédiatrique", praticien: "Dr. Sarr", heure: "11:20", statut: "termine" },
];

const statusMeta: Record<ActivityStatus, { label: string; status: "success" | "accent" | "danger" | "neutral" }> = {
  termine: { label: "Terminé", status: "success" },
  en_cours: { label: "En cours", status: "accent" },
  en_retard: { label: "En retard", status: "danger" },
  annule: { label: "Annulé", status: "neutral" },
};

const activityColumns: DataTableColumn<ActivityRow>[] = [
  { key: "patient", header: "Patient", sortable: true, accessor: (r) => r.patient },
  { key: "type", header: "Type", sortable: true, accessor: (r) => r.type },
  { key: "praticien", header: "Praticien", sortable: true, accessor: (r) => r.praticien },
  { key: "heure", header: "Heure", accessor: (r) => r.heure, align: "right" },
  {
    key: "statut",
    header: "Statut",
    align: "right",
    render: (r) => (
      <Badge status={statusMeta[r.statut].status}>{statusMeta[r.statut].label}</Badge>
    ),
  },
];

export function DashboardPage() {
  const { user } = useAuth();

  return (
    <div className="space-y-6">
      <Card className="glow-accent relative overflow-hidden p-6">
        <div className="relative">
          <p className="text-sm text-text-muted">Bienvenue,</p>
          <h2 className="font-heading text-xl font-semibold text-text">
            {user ? `${user.first_name} ${user.last_name}` : "Utilisateur"}
          </h2>
          <p className="mt-1 text-sm text-text-muted">
            Voici un aperçu de l'activité de votre structure aujourd'hui.
          </p>
        </div>
      </Card>

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <KpiCard label="Consultations du jour" value={50} variation={12} icon={Activity} />
        <KpiCard label="Patients actifs" value="1 284" variation={4} icon={Users} />
        <KpiCard label="Taux d'occupation lits" value={78} unit="%" variation={-3} icon={BedDouble} />
        <KpiCard label="Recettes du jour" value="2 340 000" unit="FCFA" variation={8} icon={Receipt} />
      </div>

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Consultations — 11 derniers jours</CardTitle>
          </CardHeader>
          <CardContent className="h-64 pl-0">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={consultationsTrend} margin={{ top: 4, right: 16, left: 0, bottom: 0 }}>
                <defs>
                  <linearGradient id="consultationsFill" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor="#3D7EFF" stopOpacity={0.35} />
                    <stop offset="100%" stopColor="#3D7EFF" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid stroke="#242938" vertical={false} />
                <XAxis
                  dataKey="jour"
                  tick={{ fill: "#5C6478", fontSize: 11 }}
                  tickLine={false}
                  axisLine={{ stroke: "#242938" }}
                />
                <YAxis tick={{ fill: "#5C6478", fontSize: 11 }} tickLine={false} axisLine={false} width={32} />
                <Tooltip
                  contentStyle={{
                    background: "#12151D",
                    border: "1px solid #242938",
                    borderRadius: 8,
                    fontSize: 12,
                    color: "#F4F5F7",
                  }}
                  labelStyle={{ color: "#9AA1B2" }}
                />
                <Area
                  type="monotone"
                  dataKey="total"
                  stroke="#3D7EFF"
                  strokeWidth={2}
                  fill="url(#consultationsFill)"
                />
              </AreaChart>
            </ResponsiveContainer>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Prochains rendez-vous</CardTitle>
          </CardHeader>
          <CardContent>
            <EmptyState
              icon={CalendarClock}
              title="Aucun rendez-vous en attente"
              description="Les prochains rendez-vous planifiés apparaîtront ici."
            />
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Activité récente</CardTitle>
        </CardHeader>
        <CardContent>
          <DataTable columns={activityColumns} data={recentActivity} rowKey={(r) => r.id} pageSize={5} />
        </CardContent>
      </Card>
    </div>
  );
}
