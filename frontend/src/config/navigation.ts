import {
  BarChart3,
  BedDouble,
  Building2,
  CalendarDays,
  FlaskConical,
  LayoutDashboard,
  Pill,
  Receipt,
  Scan,
  Settings,
  ShieldCheck,
  ShoppingCart,
  Stethoscope,
  Syringe,
  UserCog,
  Users,
  Wrench,
} from "lucide-react";
import type { NavSection } from "@/components/layout/sidebar";

export const navigationSections: NavSection[] = [
  {
    title: "Clinique",
    items: [
      { label: "Tableau de bord", href: "/dashboard", icon: LayoutDashboard },
      { label: "Patients", href: "/patients", icon: Users },
      { label: "Rendez-vous", href: "/rendez-vous", icon: CalendarDays },
      { label: "Consultations", href: "/consultations", icon: Stethoscope },
      { label: "Laboratoire", href: "/laboratoire", icon: FlaskConical },
      { label: "Imagerie", href: "/imagerie", icon: Scan },
      { label: "Hospitalisation", href: "/hospitalisation", icon: BedDouble },
      { label: "Bloc opératoire", href: "/bloc-operatoire", icon: Syringe },
    ],
  },
  {
    title: "Logistique",
    items: [
      { label: "Pharmacie", href: "/pharmacie", icon: Pill },
      { label: "Achats", href: "/achats", icon: ShoppingCart },
      { label: "Équipements biomédicaux", href: "/equipements", icon: Wrench },
    ],
  },
  {
    title: "Administration",
    items: [
      { label: "Facturation", href: "/facturation", icon: Receipt },
      { label: "Personnel", href: "/personnel", icon: UserCog },
      { label: "Structures & sites", href: "/structures", icon: Building2 },
    ],
  },
  {
    title: "Pilotage",
    items: [
      { label: "Rapports", href: "/rapports", icon: BarChart3 },
      { label: "Audit", href: "/audit", icon: ShieldCheck },
      { label: "Paramètres", href: "/parametres", icon: Settings },
    ],
  },
];
