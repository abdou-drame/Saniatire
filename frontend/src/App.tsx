import { Lock } from "lucide-react";
import { Navigate, Outlet, Route, Routes } from "react-router-dom";
import { AppLayout } from "@/components/layout/app-layout";
import { PatientProtectedRoute } from "@/components/portal/patient-protected-route";
import { PortalLayout } from "@/components/portal/portal-layout";
import { PrescriberProtectedRoute } from "@/components/portal-prescripteur/prescriber-protected-route";
import { PrescriberPortalLayout } from "@/components/portal-prescripteur/prescriber-portal-layout";
import { PlatformProtectedRoute } from "@/components/platform/platform-protected-route";
import { PlatformLayout } from "@/components/platform/platform-layout";
import { ProtectedRoute } from "@/components/protected-route";
import { EmptyState } from "@/components/ui/empty-state";
import { AuthProvider, useAuth } from "@/hooks/use-auth";
import { PatientAuthProvider } from "@/hooks/use-patient-auth";
import { PrescriberAuthProvider } from "@/hooks/use-prescriber-auth";
import { PlatformAuthProvider } from "@/hooks/use-platform-auth";
import { ConsultationsPage } from "@/pages/consultations/consultations-page";
import { DashboardPage } from "@/pages/dashboard-page";
import { DirectionPage } from "@/pages/direction/direction-page";
import { DoctorDashboardPage } from "@/pages/doctor/doctor-dashboard-page";
import { LoginPage } from "@/pages/login-page";
import { LaboratoirePage } from "@/pages/laboratoire/laboratoire-page";
import { ImageriePage } from "@/pages/imagerie/imagerie-page";
import { HospitalisationPage } from "@/pages/hospitalisation/hospitalisation-page";
import { BlocOperatoirePage } from "@/pages/bloc-operatoire/bloc-operatoire-page";
import { PharmaciePage } from "@/pages/pharmacie/pharmacie-page";
import { AchatsPage } from "@/pages/achats/achats-page";
import { EquipementsPage } from "@/pages/equipements/equipements-page";
import { FacturationPage } from "@/pages/facturation/facturation-page";
import { CaissePage } from "@/pages/caisse/caisse-page";
import { AssurancesPage } from "@/pages/assurances/assurances-page";
import { CreancesPage } from "@/pages/creances/creances-page";
import { PatientDetailPage } from "@/pages/patients/patient-detail-page";
import { PatientsDirectoryRoute } from "@/pages/patients/patients-directory-page";
import { MaternitePage } from "@/pages/specialties/maternite-page";
import { DentairePage } from "@/pages/specialties/dentaire-page";
import { DialysePage } from "@/pages/specialties/dialyse-page";
import { OphtalmoPage } from "@/pages/specialties/ophtalmo-page";
import { CardiologiePage } from "@/pages/specialties/cardiologie-page";
import { KinesitherapiePage } from "@/pages/specialties/kinesitherapie-page";
import { OncologiePage } from "@/pages/specialties/oncologie-page";
import { PmaPage } from "@/pages/specialties/pma-page";
import { SanteMentalePage } from "@/pages/specialties/sante-mentale-page";
import { PediatriePage } from "@/pages/specialties/pediatrie-page";
import { MedecineTravailPage } from "@/pages/specialties/medecine-travail-page";
import { SoinsDomicilePage } from "@/pages/specialties/soins-domicile-page";
import { PortalActivatePage } from "@/pages/portal/portal-activate-page";
import { PortalAppointmentsPage } from "@/pages/portal/appointments/portal-appointments-page";
import { PortalNewAppointmentPage } from "@/pages/portal/appointments/portal-new-appointment-page";
import { PortalDocumentsPage } from "@/pages/portal/portal-documents-page";
import { PortalForgotPasswordPage } from "@/pages/portal/portal-forgot-password-page";
import { PortalInvoiceDetailPage } from "@/pages/portal/portal-invoice-detail-page";
import { PortalInvoicesPage } from "@/pages/portal/portal-invoices-page";
import { PortalComplaintDetailPage } from "@/pages/portal/portal-complaint-detail-page";
import { PortalComplaintsPage } from "@/pages/portal/portal-complaints-page";
import { PortalNewComplaintPage } from "@/pages/portal/portal-new-complaint-page";
import { PortalLoginPage } from "@/pages/portal/portal-login-page";
import { PortalPreferencesPage } from "@/pages/portal/portal-preferences-page";
import { PortalResetPasswordPage } from "@/pages/portal/portal-reset-password-page";
import { PrescriberActivatePage } from "@/pages/portal-prescripteur/prescriber-activate-page";
import { PrescriberForgotPasswordPage } from "@/pages/portal-prescripteur/prescriber-forgot-password-page";
import { PrescriberLoginPage } from "@/pages/portal-prescripteur/prescriber-login-page";
import { PrescriberNewRequestPage } from "@/pages/portal-prescripteur/prescriber-new-request-page";
import { PrescriberRequestsPage } from "@/pages/portal-prescripteur/prescriber-requests-page";
import { PrescriberResetPasswordPage } from "@/pages/portal-prescripteur/prescriber-reset-password-page";
import { ReceptionPage } from "@/pages/reception/reception-page";
import { PersonnelRoute } from "@/pages/personnel/personnel-page";
import { UserAccountsRoute } from "@/pages/comptes/user-accounts-page";
import { ExternalPrescribersRoute } from "@/pages/prescripteurs/external-prescribers-page";
import { PlanningsRoute } from "@/pages/plannings/plannings-page";
import { CongesRoute } from "@/pages/conges/conges-page";
import { GardesAstreintesRoute } from "@/pages/gardes-astreintes/gardes-astreintes-page";
import { QualiteRoute } from "@/pages/qualite/qualite-page";
import { TeleconsultationRoute } from "@/pages/teleconsultation/teleconsultation-page";
import { StructuresPage } from "@/pages/structures/structures-page";
import { ReferencementRoute } from "@/pages/referencement/referencement-page";
import { NotificationTemplatesRoute } from "@/pages/notifications/notification-templates-page";
import { AuditRoute } from "@/pages/audit/audit-page";
import { SettingsPage } from "@/pages/settings-page";
import { PlatformLoginPage } from "@/pages/platform/platform-login-page";
import { PlatformStructuresPage } from "@/pages/platform/platform-structures-page";
import { PlatformStructureDetailPage } from "@/pages/platform/platform-structure-detail-page";
import { PlatformAuditPage } from "@/pages/platform/platform-audit-page";

const RECEPTION_ROLES = ["secretaire", "administrateur", "direction", "directeur_medical"];
/** Mirrors the backend's dashboards.direction permission grants (RolePermissionSeeder). */
const DIRECTION_ROLES = ["administrateur", "direction", "directeur_medical"];

function DashboardRoute() {
  const { hasRole } = useAuth();
  return hasRole("medecin") ? <DoctorDashboardPage /> : <DashboardPage />;
}

function ReceptionRoute() {
  const { hasRole } = useAuth();
  if (!RECEPTION_ROLES.some((role) => hasRole(role))) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel d'accueil et de secrétariat."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <ReceptionPage />;
}

function LaboratoireRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("laboratoire.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur le module Laboratoire."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <LaboratoirePage />;
}

function ConsultationsRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("consultations.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité à consulter les dossiers de consultation."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <ConsultationsPage />;
}

function StructuresRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("structures.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité à gérer la structure et ses sites."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <StructuresPage />;
}

function ImagerieRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("imagerie.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur le module Imagerie."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <ImageriePage />;
}

function HospitalisationRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("hospitalisation.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur le module Hospitalisation."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <HospitalisationPage />;
}

function BlocOperatoireRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("bloc_operatoire.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur le module Bloc opératoire."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <BlocOperatoirePage />;
}

function PharmacieRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("stock.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur le module Pharmacie/Stocks."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <PharmaciePage />;
}

function AchatsRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("achats.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur le module Achats/Fournisseurs."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <AchatsPage />;
}

function EquipementsRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("biomedical.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur le module Équipements biomédicaux."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <EquipementsPage />;
}

function FacturationRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("facturation.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur le module Facturation."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <FacturationPage />;
}

function CaisseRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("caisse.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur le module Caisse."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <CaissePage />;
}

function AssurancesRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("assurance.view")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur le module Assurances/IPM."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <AssurancesPage />;
}

function CreancesRoute() {
  const { hasPermission } = useAuth();
  if (!hasPermission("facturation.creances")) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé au personnel habilité sur le suivi des créances."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <CreancesPage />;
}

function DirectionRoute() {
  const { hasRole } = useAuth();
  if (!DIRECTION_ROLES.some((role) => hasRole(role))) {
    return (
      <div className="flex h-full items-center justify-center">
        <EmptyState
          icon={Lock}
          title="Accès non autorisé"
          description="Cet écran est réservé aux rôles de direction."
          className="w-full max-w-md py-24"
        />
      </div>
    );
  }
  return <DirectionPage />;
}

export default function App() {
  return (
    <Routes>
      {/*
        Branche professionnelle et branche patient sont sœurs, jamais
        imbriquées : AuthProvider (staff) et PatientAuthProvider (patient)
        ne se retrouvent donc jamais dans le même sous-arbre React, ce qui
        garantit qu'aucun composant ne peut accidentellement lire les deux
        contextes d'authentification à la fois.
      */}
      <Route element={<AuthProvider><Outlet /></AuthProvider>}>
        <Route path="/login" element={<LoginPage />} />

        <Route
          element={
            <ProtectedRoute>
              <AppLayout />
            </ProtectedRoute>
          }
        >
          <Route path="/dashboard" element={<DashboardRoute />} />
          <Route path="/patients" element={<PatientsDirectoryRoute />} />
          <Route path="/patients/:id" element={<PatientDetailPage />} />
          <Route path="/patients/:id/maternite" element={<MaternitePage />} />
          <Route path="/patients/:id/dentaire" element={<DentairePage />} />
          <Route path="/patients/:id/dialyse" element={<DialysePage />} />
          <Route path="/patients/:id/ophtalmo" element={<OphtalmoPage />} />
          <Route path="/patients/:id/cardiologie" element={<CardiologiePage />} />
          <Route path="/patients/:id/kinesitherapie" element={<KinesitherapiePage />} />
          <Route path="/patients/:id/oncologie" element={<OncologiePage />} />
          <Route path="/patients/:id/pma" element={<PmaPage />} />
          <Route path="/patients/:id/sante-mentale" element={<SanteMentalePage />} />
          <Route path="/patients/:id/pediatrie" element={<PediatriePage />} />
          <Route path="/patients/:id/medecine-travail" element={<MedecineTravailPage />} />
          <Route path="/patients/:id/soins-domicile" element={<SoinsDomicilePage />} />
          <Route path="/rendez-vous" element={<ReceptionRoute />} />
          <Route path="/consultations" element={<ConsultationsRoute />} />
          <Route path="/teleconsultations" element={<TeleconsultationRoute />} />
          <Route path="/referencement" element={<ReferencementRoute />} />
          <Route path="/templates-notification" element={<NotificationTemplatesRoute />} />
          <Route path="/laboratoire" element={<LaboratoireRoute />} />
          <Route path="/imagerie" element={<ImagerieRoute />} />
          <Route path="/hospitalisation" element={<HospitalisationRoute />} />
          <Route path="/bloc-operatoire" element={<BlocOperatoireRoute />} />
          <Route path="/pharmacie" element={<PharmacieRoute />} />
          <Route path="/achats" element={<AchatsRoute />} />
          <Route path="/equipements" element={<EquipementsRoute />} />
          <Route path="/facturation" element={<FacturationRoute />} />
          <Route path="/caisse" element={<CaisseRoute />} />
          <Route path="/assurances" element={<AssurancesRoute />} />
          <Route path="/creances" element={<CreancesRoute />} />
          <Route path="/personnel" element={<PersonnelRoute />} />
          <Route path="/comptes-utilisateurs" element={<UserAccountsRoute />} />
          <Route path="/prescripteurs-externes" element={<ExternalPrescribersRoute />} />
          <Route path="/plannings" element={<PlanningsRoute />} />
          <Route path="/conges" element={<CongesRoute />} />
          <Route path="/gardes-astreintes" element={<GardesAstreintesRoute />} />
          <Route path="/qualite" element={<QualiteRoute />} />
          <Route path="/structures" element={<StructuresRoute />} />
          <Route path="/rapports" element={<DirectionRoute />} />
          <Route path="/audit" element={<AuditRoute />} />
          <Route path="/parametres" element={<SettingsPage />} />
        </Route>

        <Route path="/" element={<Navigate to="/dashboard" replace />} />
      </Route>

      <Route path="/portail" element={<PatientAuthProvider><Outlet /></PatientAuthProvider>}>
        <Route path="login" element={<PortalLoginPage />} />
        <Route path="activer" element={<PortalActivatePage />} />
        <Route path="mot-de-passe-oublie" element={<PortalForgotPasswordPage />} />
        <Route path="reinitialiser-mot-de-passe" element={<PortalResetPasswordPage />} />

        <Route
          element={
            <PatientProtectedRoute>
              <PortalLayout />
            </PatientProtectedRoute>
          }
        >
          <Route path="rendez-vous" element={<PortalAppointmentsPage />} />
          <Route path="rendez-vous/nouveau" element={<PortalNewAppointmentPage />} />
          <Route path="documents" element={<PortalDocumentsPage />} />
          <Route path="factures" element={<PortalInvoicesPage />} />
          <Route path="factures/:id" element={<PortalInvoiceDetailPage />} />
          <Route path="reclamations" element={<PortalComplaintsPage />} />
          <Route path="reclamations/nouveau" element={<PortalNewComplaintPage />} />
          <Route path="reclamations/:id" element={<PortalComplaintDetailPage />} />
          <Route path="preferences" element={<PortalPreferencesPage />} />
          <Route index element={<Navigate to="rendez-vous" replace />} />
        </Route>

        <Route path="*" element={<Navigate to="login" replace />} />
      </Route>

      {/*
        Troisième branche sœur, isolée au même titre que les deux
        précédentes : PrescriberAuthProvider (token/state prescripteur) ne
        se retrouve jamais dans le même sous-arbre que AuthProvider ou
        PatientAuthProvider.
      */}
      <Route path="/portail-prescripteur" element={<PrescriberAuthProvider><Outlet /></PrescriberAuthProvider>}>
        <Route path="login" element={<PrescriberLoginPage />} />
        <Route path="activer" element={<PrescriberActivatePage />} />
        <Route path="mot-de-passe-oublie" element={<PrescriberForgotPasswordPage />} />
        <Route path="reinitialiser-mot-de-passe" element={<PrescriberResetPasswordPage />} />

        <Route
          element={
            <PrescriberProtectedRoute>
              <PrescriberPortalLayout />
            </PrescriberProtectedRoute>
          }
        >
          <Route path="demandes" element={<PrescriberRequestsPage />} />
          <Route path="demandes/nouvelle" element={<PrescriberNewRequestPage />} />
          <Route index element={<Navigate to="demandes" replace />} />
        </Route>

        <Route path="*" element={<Navigate to="login" replace />} />
      </Route>

      {/*
        Quatrième branche sœur — administration plateforme, guard `platform`
        distinct de sanctum/patient/prescripteur : PlatformAuthProvider ne
        se retrouve jamais dans le même sous-arbre que les trois autres
        providers, et réciproquement aucune des trois autres branches ne
        peut atteindre une route /platform.
      */}
      <Route path="/platform" element={<PlatformAuthProvider><Outlet /></PlatformAuthProvider>}>
        <Route path="login" element={<PlatformLoginPage />} />

        <Route
          element={
            <PlatformProtectedRoute>
              <PlatformLayout />
            </PlatformProtectedRoute>
          }
        >
          <Route path="structures" element={<PlatformStructuresPage />} />
          <Route path="structures/:id" element={<PlatformStructureDetailPage />} />
          <Route path="audit" element={<PlatformAuditPage />} />
          <Route index element={<Navigate to="structures" replace />} />
        </Route>

        <Route path="*" element={<Navigate to="login" replace />} />
      </Route>

      <Route path="*" element={<Navigate to="/dashboard" replace />} />
    </Routes>
  );
}
