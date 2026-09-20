export interface Paginated<T> {
  data: T[];
  meta: {
    current_page: number;
    from: number | null;
    last_page: number;
    per_page: number;
    to: number | null;
    total: number;
  };
  links: {
    first: string | null;
    last: string | null;
    prev: string | null;
    next: string | null;
  };
}

export interface Site {
  id: number;
  structure_id?: number;
  name: string;
  code?: string;
  address?: string | null;
  city?: string | null;
  phone?: string | null;
  email?: string | null;
  opening_hours?: Record<string, unknown> | null;
  is_active?: boolean;
  created_at?: string;
  updated_at?: string;
}

export interface AuthenticatedUser {
  id: number;
  structure_id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone?: string | null;
  is_active: boolean;
  must_change_password: boolean;
  two_factor_enabled: boolean;
  two_factor_required: boolean;
  roles: string[];
  permissions: string[];
  sites: Site[];
  structure_name?: string | null;
}

/**
 * Forme complète de UserResource — utilisée par l'écran de gestion des
 * comptes (liste/édition), distincte de AuthenticatedUser (le compte
 * courant) et de StaffUser (répertoire minimal id/nom/rôles utilisé pour
 * résoudre des références ailleurs dans l'app).
 */
export interface UserAccount {
  id: number;
  structure_id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone: string | null;
  photo_path: string | null;
  is_active: boolean;
  last_login_at: string | null;
  two_factor_enabled: boolean;
  two_factor_required: boolean;
  roles: string[];
  permissions: string[];
  sites: Site[];
  created_at: string;
  updated_at: string;
}

export type QueuePriority = "normale" | "urgente" | "tres_urgente";
export type QueueStatus = "en_attente" | "appele" | "en_consultation" | "sorti";

export interface QueueEntry {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  appointment_id: number | null;
  practitioner_id: number | null;
  service: string;
  priority: QueuePriority;
  status: QueueStatus;
  arrived_at: string;
  called_at: string | null;
  in_consultation_at: string | null;
  exited_at: string | null;
  wait_minutes: number | null;
}

export interface PatientAllergy {
  id: number;
  patient_id: number;
  allergen: string;
  severity: string | null;
  reaction: string | null;
  notes: string | null;
}

export interface PatientMedicalInfo {
  id: number;
  patient_id: number;
  blood_group: string | null;
  medical_history: string | null;
  chronic_diseases: string | null;
  current_treatments: string | null;
}

export interface Patient {
  id: number;
  structure_id: number;
  patient_number: string;
  first_name: string;
  last_name: string;
  sex: "M" | "F";
  birth_date: string;
  phone: string | null;
  email: string | null;
  address: string | null;
  profession: string | null;
  nationality: string | null;
  emergency_contact_name: string | null;
  emergency_contact_phone: string | null;
  emergency_contact_relationship: string | null;
  medical_info?: PatientMedicalInfo | null;
  allergies?: PatientAllergy[];
  portal_activated_at: string | null;
}

export type DiagnosisType = "principal" | "secondaire";
export type DiagnosisStatus = "provisoire" | "confirme";

export interface ConsultationDiagnosis {
  id: number;
  consultation_id: number;
  icd_code_id: number;
  code: string;
  label: string;
  version: string;
  type: DiagnosisType;
  status: DiagnosisStatus;
  created_at: string;
}

export interface ConsultationVitals {
  weight_kg: number | null;
  height_cm: number | null;
  bmi: number | null;
  temperature_c: number | null;
  blood_pressure_systolic: number | null;
  blood_pressure_diastolic: number | null;
  heart_rate: number | null;
  respiratory_rate: number | null;
  spo2: number | null;
  glycemia: number | null;
  pain_scale: number | null;
  extra: Record<string, unknown> | null;
}

export type ConsultationStatus = "en_cours" | "terminee";

export interface Consultation {
  id: number;
  structure_id: number;
  patient_id: number;
  practitioner_id: number;
  site_id: number;
  appointment_id: number | null;
  specialty_type: string | null;
  specialty: unknown;
  reason: string;
  history_of_illness: string | null;
  vitals: ConsultationVitals;
  clinical_exam: string | null;
  recommendations: string | null;
  referral: string | null;
  follow_up_suggested_at: string | null;
  status: ConsultationStatus;
  closed_at: string | null;
  diagnoses: ConsultationDiagnosis[];
  created_at: string;
  updated_at: string;
}

export interface IcdCode {
  id: number;
  code: string;
  version: string;
  label: string;
  parent_id: number | null;
  level: "chapitre" | "groupe" | "code";
  status: string;
}

export type AppointmentStatus = "planifie" | "confirme" | "en_cours" | "termine" | "annule" | "absent";

export interface Appointment {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  practitioner_id: number;
  appointment_series_id: number | null;
  resource_name: string | null;
  starts_at: string;
  duration_minutes: number;
  reason: string | null;
  status: AppointmentStatus;
  is_recurring: boolean;
  created_at: string;
  updated_at: string;
}

export interface Practitioner {
  id: number;
  first_name: string;
  last_name: string;
}

export type TimelineEventType =
  | "consultation"
  | "activity"
  | "lab_order"
  | "imaging_order"
  | "hospitalization"
  | "surgical_procedure";

export interface TimelineEvent {
  type: TimelineEventType;
  date: string;
  summary: string;
  data: Consultation | LabOrder | ImagingOrder | Hospitalization | SurgicalProcedure | Record<string, unknown>;
}

export type CreancesBucket = "0-30" | "31-60" | "61-90" | "90+";

export interface BalanceAgeeLigne {
  invoice_id: number;
  numero: string;
  patient_id: number;
  patient_label: string | null;
  insurance_convention_id: number | null;
  insurance_provider_label: string | null;
  date_emission: string;
  anciennete_jours: number;
  solde: number;
  bucket: CreancesBucket;
}

export interface BalanceAgee {
  buckets: Record<CreancesBucket, number>;
  lignes: BalanceAgeeLigne[];
}

export interface FinancialDashboard {
  meta: { from: string; to: string; site_id: number | null };
  encaissements_total: number;
  encaissements_par_site: { site_id: number | null; total: number }[];
  encaissements_par_mode_paiement: { mode_paiement: string; total: number }[];
  recettes_facturees_par_prestation: { categorie: string; montant_total: number }[];
  balance_agee: BalanceAgee;
  taux_recouvrement: number | null;
  repartition_assureur_patient: {
    par_assureur: { insurance_provider_id: number; nom: string; montant: number }[];
    part_patient: number;
  };
}

export interface IcdStatRow {
  code?: string;
  label?: string;
  chapter_code?: string;
  chapter_label?: string;
  total: number;
}

export interface MedicalDashboard {
  meta: { from: string; to: string; site_id: number | null };
  consultations: {
    total: number;
    par_praticien: { practitioner_id: number; total: number }[];
    par_specialite: { specialty_type: string | null; total: number }[];
  };
  epidemiologie: {
    from: string;
    to: string;
    group_by: "code" | "chapter";
    site_id: number | null;
    stats: IcdStatRow[];
  };
  occupation_lits: {
    ward_id: number;
    ward_name: string;
    site_id: number;
    total_beds: number;
    occupied_beds: number;
    occupancy_rate: number;
  }[];
  temps_attente: {
    from: string;
    to: string;
    entries_count: number;
    average_wait_minutes: number | null;
  };
  actes_par_specialite: {
    site_filtre_applique: boolean;
    data: { categorie: string; total_actes: number; montant_total: number }[];
  };
}

export interface DirectionDashboard {
  meta: { from: string; to: string };
  consolide: {
    ca_total: number;
    nombre_patients: number;
    taux_occupation_moyen: number | null;
  };
  comparaison_sites: {
    site_id: number;
    site_name: string;
    ca: number;
    nombre_patients: number;
    taux_occupation: number | null;
    rang: number;
  }[];
}

export interface PatientUser {
  id: number;
  structure_id: number;
  patient_number: string;
  first_name: string;
  last_name: string;
  sex: "M" | "F";
  birth_date: string;
  phone: string | null;
  email: string | null;
  address: string | null;
  profession: string | null;
  nationality: string | null;
  emergency_contact_name: string | null;
  emergency_contact_phone: string | null;
  emergency_contact_relationship: string | null;
  photo_path: string | null;
  id_document_path: string | null;
  portal_activated_at: string | null;
  created_at: string;
  updated_at: string;
}

export type InvoiceStatus = "brouillon" | "emise" | "partiellement_payee" | "payee" | "annulee";

export interface InvoiceItem {
  id: number;
  invoice_id: number;
  billable_item_id: number | null;
  libelle: string;
  categorie: string | null;
  quantite: number;
  prix_unitaire: number;
  montant_total: number;
  taux_couverture_applique: number | null;
  montant_assurance: number;
  montant_patient: number;
}

export type ModePaiement = "especes" | "carte" | "virement" | "mobile_money";
export type StatutMobileMoney = "pending" | "confirmed" | "failed";

export interface Payment {
  id: number;
  structure_id: number;
  site_id: number;
  invoice_id: number;
  cash_session_id: number | null;
  caissier_id: number;
  caissier_label: string | null;
  mode_paiement: ModePaiement;
  reference_transaction: string | null;
  statut_mobile_money: StatutMobileMoney | null;
  montant: number;
  numero_recu: string | null;
  paid_at: string | null;
  invoice?: { id: number; numero: string; patient_id: number } | null;
  created_at: string;
}

export interface Invoice {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  insurance_convention_id: number | null;
  insurance_convention_label?: string | null;
  numero: string;
  date_emission: string;
  date_echeance: string | null;
  montant_total: number;
  montant_part_patient: number;
  montant_part_assurance: number;
  statut: InvoiceStatus;
  patient?: { id: number; first_name: string; last_name: string; patient_number: string } | null;
  site?: { id: number; name: string } | null;
  items?: InvoiceItem[];
  payments?: Payment[];
  created_at: string;
  updated_at: string;
}

export interface ServiceTariff {
  id: number;
  structure_id: number;
  code: string;
  libelle: string;
  categorie: string;
  prix_unitaire: number;
  actif: boolean;
  created_at: string;
  updated_at: string;
}

export type QuoteStatut = "brouillon" | "emis" | "converti" | "expire" | "annule";

export interface QuoteItem {
  id: number;
  quote_id: number;
  billable_item_id: number | null;
  libelle: string;
  categorie: string;
  quantite: number;
  prix_unitaire: number;
  montant_total: number;
}

export interface Quote {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  insurance_convention_id: number | null;
  converted_invoice_id: number | null;
  numero: string;
  date_emission: string;
  montant_total: number;
  statut: QuoteStatut;
  patient?: { id: number; first_name: string; last_name: string; patient_number: string } | null;
  site?: { id: number; name: string } | null;
  items?: QuoteItem[];
  created_at: string;
  updated_at: string;
}

export type BillableItemStatut = "a_facturer" | "facturee" | "annulee" | "a_tarifer";

export interface BillableItem {
  id: number;
  structure_id: number;
  patient_id: number;
  billable_type: string;
  billable_id: number;
  categorie: string;
  code_prestation: string;
  libelle: string;
  quantite: number;
  prix_unitaire: number;
  montant_total: number;
  statut: BillableItemStatut;
  created_at: string;
  updated_at: string;
}

export type CashSessionStatut = "ouverte" | "fermee";

export interface CashSession {
  id: number;
  structure_id: number;
  site_id: number;
  caissier_id: number;
  caissier_label: string | null;
  montant_ouverture: number;
  montant_cloture: number | null;
  ecart: number | null;
  ouverte_le: string;
  fermee_le: string | null;
  statut: CashSessionStatut;
  site?: { id: number; name: string } | null;
  created_at: string;
  updated_at: string;
}

export type InsuranceProviderType = "assurance_privee" | "ipm" | "mutuelle";

export interface InsuranceConventionCoverageRule {
  id: number;
  insurance_convention_id: number;
  categorie: string;
  taux_couverture: number;
  plafond_montant: number | null;
  exclu: boolean;
  created_at: string;
  updated_at: string;
}

export interface InsuranceConvention {
  id: number;
  structure_id: number;
  insurance_provider_id: number;
  nom: string;
  date_debut: string;
  date_fin: string | null;
  actif: boolean;
  coverage_rules?: InsuranceConventionCoverageRule[];
  created_at: string;
  updated_at: string;
}

export interface InsuranceProvider {
  id: number;
  structure_id: number;
  nom: string;
  type: InsuranceProviderType;
  contact: string | null;
  conventions?: InsuranceConvention[];
  created_at: string;
  updated_at: string;
}

export type BeneficiaireType = "assure_principal" | "ayant_droit";

export interface PatientInsuranceCoverage {
  id: number;
  structure_id: number;
  patient_id: number;
  insurance_convention_id: number;
  numero_adherent: string;
  beneficiaire_type: BeneficiaireType;
  date_debut: string;
  date_fin: string | null;
  actif: boolean;
  patient?: { id: number; first_name: string; last_name: string; patient_number: string } | null;
  convention?: { id: number; nom: string; provider_nom: string | null } | null;
  created_at: string;
  updated_at: string;
}

export type NotificationChannel = "email" | "sms" | "whatsapp" | "push";

export interface NotificationPreference {
  id: number;
  notifiable_type: string;
  notifiable_id: number;
  canaux: NotificationChannel[];
  created_at: string;
  updated_at: string;
}

export interface LabResultDocument {
  id: number;
  type: string | null;
  date: string | null;
  praticien: string | null;
  value: string | null;
  unit: string | null;
  reference_min: string | null;
  reference_max: string | null;
  interpretation: string | null;
}

export interface ImagingReportDocument {
  id: number;
  type: string | null;
  date: string | null;
  praticien: string | null;
  content: string | null;
}

export interface PatientDocuments {
  resultats_laboratoire: LabResultDocument[];
  comptes_rendus_imagerie: ImagingReportDocument[];
}

export interface PrescriberUser {
  id: number;
  structure_id: number;
  nom: string;
  specialite: string | null;
  email: string;
  telephone: string | null;
  statut: "actif" | "inactif";
  portal_activated_at: string | null;
  created_at: string;
  updated_at: string;
}

export interface PrescriberPatientSearchResult {
  id: number;
  first_name: string;
  last_name: string;
  patient_number: string;
  birth_date: string;
}

export interface PrescriberLoincCode {
  id: number;
  code: string;
  label: string;
  component: string | null;
  default_unit: string | null;
  version: string | null;
  status: string;
}

export type LabOrderStatus =
  | "demande"
  | "prelevement_effectue"
  | "en_analyse"
  | "resultats_disponibles"
  | "transmis"
  | "annule";

export type LabResultStatus =
  | "validation_technique_attente"
  | "validation_biologique_attente"
  | "valide"
  | "transmis";

export interface LabResult {
  id: number;
  lab_sample_id: number;
  lab_order_item_id: number;
  value: string;
  unit: string | null;
  reference_min: number | null;
  reference_max: number | null;
  interpretation: "normal" | "anormal" | "critique" | null;
  status: LabResultStatus;
  technical_validated_by: number | null;
  technical_validator_label: string | null;
  technical_validator_role: string | null;
  technical_validated_at: string | null;
  biological_validated_by: number | null;
  biological_validator_label: string | null;
  biological_validator_role: string | null;
  biological_validated_at: string | null;
  created_at: string;
}

export interface LabOrderItem {
  id: number;
  lab_order_id: number;
  status: string;
  loinc_code: PrescriberLoincCode | null;
  result: LabResult | null;
}

export interface LabSample {
  id: number;
  lab_order_id: number;
  barcode: string;
  sample_type: string;
  collected_at: string;
  collected_by: number | null;
  results: LabResult[];
}

export interface LabOrder {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  requester_type: string;
  requester_id: number;
  consultation_id: number | null;
  status: LabOrderStatus;
  billing_status: string;
  ordered_at: string;
  notes: string | null;
  items: LabOrderItem[];
  samples: LabSample[];
  patient: { id: number; first_name: string; last_name: string; patient_number: string } | null;
  requester_label: string | null;
  site: { id: number; name: string } | null;
  created_at: string;
  updated_at: string;
}

export interface LabOrderStats {
  demandes_en_attente: number;
  prelevements_du_jour: number;
  resultats_en_attente_validation: number;
  resultats_transmis_aujourdhui: number;
}

export type ImagingExamType = "radio" | "echo" | "scanner" | "irm";

export type ImagingOrderStatus =
  | "demande"
  | "planifie"
  | "realise"
  | "en_interpretation"
  | "cr_redige"
  | "valide"
  | "transmis"
  | "annule";

export type ImagingStudyStatus = "realise" | "en_interpretation" | "cr_redige" | "valide" | "transmis";

export type ImagingReportStatus = "brouillon" | "valide";

export interface ImagingReport {
  id: number;
  imaging_study_id: number;
  author_id: number;
  author_label: string | null;
  author_role: string | null;
  content: string;
  status: ImagingReportStatus;
  validated_at: string | null;
  validated_by: number | null;
  validator_label: string | null;
  validator_role: string | null;
  created_at: string;
  updated_at: string;
}

export interface ImagingStudy {
  id: number;
  imaging_order_id: number;
  study_instance_uid: string;
  accession_number: string;
  modality: string;
  performed_at: string;
  performed_by: number | null;
  external_reference_url: string | null;
  storage_reference: string | null;
  status: ImagingStudyStatus;
  report: ImagingReport | null;
  created_at: string;
  updated_at: string;
}

export interface ImagingOrder {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  requester_type: string;
  requester_id: number;
  consultation_id: number | null;
  appointment_id: number | null;
  exam_type: ImagingExamType;
  status: ImagingOrderStatus;
  billing_status: string;
  ordered_at: string;
  notes: string | null;
  studies: ImagingStudy[];
  patient: { id: number; first_name: string; last_name: string; patient_number: string } | null;
  requester_label: string | null;
  site: { id: number; name: string } | null;
  created_at: string;
  updated_at: string;
}

export interface ImagingOrderStats {
  examens_en_attente: number;
  realises_aujourdhui: number;
  comptes_rendus_en_attente_validation: number;
  transmis_aujourdhui: number;
}

export type PrescriberLabOrderStatus =
  | "demande"
  | "prelevement_effectue"
  | "en_analyse"
  | "resultats_disponibles"
  | "transmis"
  | "annule";

export type PrescriberImagingOrderStatus =
  | "demande"
  | "planifie"
  | "realise"
  | "en_interpretation"
  | "cr_redige"
  | "valide"
  | "transmis"
  | "annule";

export interface PrescriberRequestPatient {
  id: number;
  first_name: string;
  last_name: string;
  patient_number: string;
}

export interface PrescriberLabOrderItem {
  id: number;
  label: string | null;
  code: string | null;
  value: string | null;
  unit: string | null;
  interpretation: string | null;
}

export interface PrescriberLabOrder {
  id: number;
  status: PrescriberLabOrderStatus;
  ordered_at: string | null;
  notes: string | null;
  site: { id: number; name: string } | null;
  patient: PrescriberRequestPatient | null;
  items: PrescriberLabOrderItem[];
}

export interface PrescriberImagingStudy {
  id: number;
  modality: string | null;
  performed_at: string | null;
  report: string | null;
}

export interface PrescriberImagingOrder {
  id: number;
  status: PrescriberImagingOrderStatus;
  exam_type: "radio" | "echo" | "scanner" | "irm";
  ordered_at: string | null;
  notes: string | null;
  site: { id: number; name: string } | null;
  patient: PrescriberRequestPatient | null;
  studies: PrescriberImagingStudy[];
}

export interface QualiteDashboard {
  meta: { from: string; to: string; service: string | null };
  score_moyen_satisfaction: number | null;
  reclamations: {
    par_motif: { motif: string; total: number }[];
    par_statut: { statut: string; total: number }[];
  };
  delai_moyen_resolution_heures: number | null;
}

export type BedStatus = "libre" | "occupe" | "reserve" | "entretien" | "indisponible";

export interface Bed {
  id: number;
  structure_id: number;
  site_id: number;
  ward_id: number;
  room_number: string;
  bed_label: string;
  status: BedStatus;
  created_at: string;
  updated_at: string;
}

export interface Ward {
  id: number;
  structure_id: number;
  site_id: number;
  name: string;
  beds: Bed[];
  created_at: string;
  updated_at: string;
}

export type HospitalizationStatus = "en_cours" | "sorti" | "transfere";

export interface HospitalizationDailyNote {
  id: number;
  hospitalization_id: number;
  author_id: number;
  note_date: string;
  care_administered: string | null;
  medications_given: string | null;
  procedures_performed: string | null;
  observations: string | null;
  created_at: string;
}

export interface Hospitalization {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  bed_id: number;
  ward_id: number;
  attending_physician_id: number;
  admitted_at: string;
  admission_reason: string;
  discharged_at: string | null;
  discharge_summary: string | null;
  status: HospitalizationStatus;
  daily_notes: HospitalizationDailyNote[];
  patient: { id: number; first_name: string; last_name: string; patient_number: string } | null;
  bed: { id: number; room_number: string; bed_label: string } | null;
  ward: { id: number; name: string } | null;
  attending_physician_label: string | null;
  created_at: string;
  updated_at: string;
}

export interface HospitalizationStats {
  lits_occupes: number;
  lits_total: number;
  taux_occupation: number;
  admissions_du_jour: number;
  sorties_du_jour: number;
}

export type SurgicalProcedureStatus = "planifiee" | "en_cours" | "terminee" | "annulee";

export type SurgicalChecklistStep = "avant_anesthesie" | "avant_incision" | "avant_sortie_bloc";

export interface SurgicalChecklist {
  id: number;
  surgical_procedure_id: number;
  step: SurgicalChecklistStep;
  items: Record<string, unknown>[];
  validated_by: number | null;
  validator_label: string | null;
  validator_role: string | null;
  validated_at: string | null;
  created_at: string;
}

export interface SurgicalProcedure {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  hospitalization_id: number | null;
  surgeon_id: number;
  anesthesiologist_id: number;
  operating_room: string;
  procedure_type: string;
  scheduled_at: string;
  performed_at: string | null;
  status: SurgicalProcedureStatus;
  checklists: SurgicalChecklist[];
  patient: { id: number; first_name: string; last_name: string; patient_number: string } | null;
  surgeon_label: string | null;
  anesthesiologist_label: string | null;
  created_at: string;
  updated_at: string;
}

export type ProductCategorie = "medicament" | "consommable" | "dispositif_medical";

export interface Product {
  id: number;
  structure_id: number;
  generic_catalog_ref: string | null;
  nom_commercial: string;
  dci: string | null;
  forme_galenique: string | null;
  dosage: string | null;
  categorie: ProductCategorie;
  unite_vente: string;
  actif: boolean;
  stock_total: number | null;
  created_at: string;
  updated_at: string;
}

export interface ProductBatch {
  id: number;
  structure_id: number;
  product_id: number;
  site_id: number;
  supplier_id: number | null;
  numero_lot: string;
  date_peremption: string;
  quantite_stock: number;
  prix_achat_unitaire: number | null;
  product: { id: number; nom_commercial: string; dci: string | null; unite_vente: string } | null;
  site: { id: number; name: string } | null;
  supplier: { id: number; nom: string } | null;
  created_at: string;
  updated_at: string;
}

export type StockMovementType = "entree" | "sortie" | "ajustement" | "transfert";

export interface StockMovement {
  id: number;
  structure_id: number;
  product_batch_id: number;
  site_id: number;
  destination_site_id: number | null;
  user_id: number;
  type: StockMovementType;
  quantite: number;
  motif: string | null;
  dispensed_for_type: string | null;
  dispensed_for_id: number | null;
  product_batch: { id: number; numero_lot: string; product: { id: number; nom_commercial: string } | null } | null;
  site: { id: number; name: string } | null;
  destination_site: { id: number; name: string } | null;
  user_label: string | null;
  created_at: string;
}

export interface StockThreshold {
  id: number;
  structure_id: number;
  product_id: number;
  site_id: number;
  seuil_minimum: number;
  created_at: string;
  updated_at: string;
}

export interface StockLowThresholdAlert {
  product_id: number;
  site_id: number;
  seuil_minimum: number;
  stock_actuel: number;
  product: { id: number; nom_commercial: string; dci: string | null; unite_vente: string } | null;
  site: { id: number; name: string } | null;
}

export interface Supplier {
  id: number;
  structure_id: number;
  nom: string;
  contact: string | null;
  conditions_commerciales: string | null;
  created_at: string;
  updated_at: string;
}

export type PurchaseRequestStatut = "demandee" | "validee" | "rejetee" | "commandee";

export interface PurchaseRequest {
  id: number;
  structure_id: number;
  site_id: number;
  product_id: number;
  demandeur_id: number;
  quantite: number;
  statut: PurchaseRequestStatut;
  product: { id: number; nom_commercial: string; dci: string | null; unite_vente: string } | null;
  site: { id: number; name: string } | null;
  demandeur_label: string | null;
  created_at: string;
  updated_at: string;
}

export interface ApprovalRule {
  id: number;
  structure_id: number;
  level: number;
  min_amount: number;
  role_name: string;
  created_at: string;
  updated_at: string;
}

export type PurchaseOrderStatut =
  | "brouillon"
  | "en_attente_validation"
  | "validee"
  | "envoyee"
  | "recue_partielle"
  | "recue_totale"
  | "annulee";

export interface PurchaseOrderItem {
  id: number;
  purchase_order_id: number;
  purchase_request_id: number | null;
  product_id: number;
  quantite_commandee: number;
  prix_unitaire: number;
  quantite_recue: number;
}

export type PurchaseOrderApprovalStatut = "en_attente" | "validee";

export interface PurchaseOrderApproval {
  id: number;
  purchase_order_id: number;
  approval_rule_id: number | null;
  level: number;
  min_amount: number;
  role_name: string;
  statut: PurchaseOrderApprovalStatut;
  approved_by: number | null;
  approved_at: string | null;
}

export interface PurchaseOrder {
  id: number;
  structure_id: number;
  site_id: number;
  supplier_id: number;
  created_by: number;
  montant_total: number;
  statut: PurchaseOrderStatut;
  items: PurchaseOrderItem[];
  approvals: PurchaseOrderApproval[];
  supplier: { id: number; nom: string } | null;
  site: { id: number; name: string } | null;
  created_by_label: string | null;
  created_at: string;
  updated_at: string;
}

export type ReceptionControleQualite = "conforme" | "non_conforme";

export interface PurchaseOrderReception {
  id: number;
  purchase_order_item_id: number;
  receptionne_par: number;
  quantite_recue: number;
  date_reception: string;
  controle_qualite: ReceptionControleQualite;
  numero_lot: string | null;
  date_peremption: string | null;
}

export type BiomedicalEquipmentStatut = "en_service" | "en_maintenance" | "hors_service" | "reforme";

export type MaintenanceType = "preventive" | "corrective";

export type MaintenanceStatut = "planifiee" | "realisee" | "annulee";

export interface EquipmentMaintenance {
  id: number;
  structure_id: number;
  biomedical_equipment_id: number;
  intervenant_user_id: number | null;
  type: MaintenanceType;
  date_prevue: string;
  date_realisee: string | null;
  intervenant_externe: string | null;
  cout: number | null;
  description: string | null;
  statut: MaintenanceStatut;
  equipment: { id: number; nom: string } | null;
  intervenant_label: string | null;
  created_at: string;
  updated_at: string;
}

export interface BiomedicalEquipment {
  id: number;
  structure_id: number;
  site_id: number;
  supplier_id: number | null;
  nom: string;
  categorie: string;
  numero_serie: string;
  date_acquisition: string | null;
  date_fin_garantie: string | null;
  statut: BiomedicalEquipmentStatut;
  maintenances: EquipmentMaintenance[];
  site: { id: number; name: string } | null;
  supplier: { id: number; nom: string } | null;
  created_at: string;
  updated_at: string;
}

export type StatutEmploi = "actif" | "en_conge" | "suspendu" | "termine";

export interface EmployeeProfile {
  id: number;
  structure_id: number;
  user_id: number;
  date_embauche: string | null;
  type_contrat: string | null;
  statut_emploi: StatutEmploi;
  qualification: string | null;
  numero_ordre: string | null;
  created_at: string;
  updated_at: string;
}

export type WorkScheduleType = "normal" | "garde" | "astreinte";

export interface WorkSchedule {
  id: number;
  structure_id: number;
  user_id: number;
  site_id: number;
  jour_semaine: number | null;
  date: string | null;
  heure_debut: string;
  heure_fin: string;
  type: WorkScheduleType;
  created_at: string;
  updated_at: string;
}

export type LeaveRequestType = "conge_annuel" | "maladie" | "autre";
export type LeaveRequestStatut = "demande" | "valide" | "refuse";

export interface LeaveRequest {
  id: number;
  structure_id: number;
  user_id: number;
  type: LeaveRequestType;
  date_debut: string;
  date_fin: string;
  statut: LeaveRequestStatut;
  validated_by: number | null;
  commentaire: string | null;
  created_at: string;
  updated_at: string;
}

export interface LeaveRequestOverlapWarning {
  type: "overlap_schedule";
  work_schedule_id: number;
  date: string;
  schedule_type: WorkScheduleType;
}

export interface PractitionerPlanningHoraire {
  date: string;
  work_schedule_id: number;
  site_id: number;
  type: WorkScheduleType;
  heure_debut: string;
  heure_fin: string;
}

export interface PractitionerPlanning {
  horaires: PractitionerPlanningHoraire[];
  jours_conges: string[];
  conges: LeaveRequest[];
}

export interface OnCallEntry {
  date: string;
  user_id: number;
  site_id: number;
  type: Extract<WorkScheduleType, "garde" | "astreinte">;
  heure_debut: string;
  heure_fin: string;
}

export interface StaffUser {
  id: number;
  first_name: string;
  last_name: string;
  roles: string[];
}

export type ComplaintStatut = "ouverte" | "en_cours" | "resolue" | "close";
export type ComplaintOrigin = "staff" | "patient";

export interface ComplaintResponse {
  id: number;
  auteur_id: number;
  auteur_label: string | null;
  auteur_role: string | null;
  message: string;
  visible_patient: boolean;
  created_at: string;
}

export interface Complaint {
  id: number;
  patient_id: number;
  gestionnaire_id: number | null;
  gestionnaire_label: string | null;
  gestionnaire_role: string | null;
  motif: string;
  description: string;
  service_concerne: string | null;
  origin: ComplaintOrigin;
  statut: ComplaintStatut;
  resolved_at: string | null;
  resolved_by: number | null;
  resolved_by_label: string | null;
  closed_at: string | null;
  closed_by: number | null;
  closed_by_label: string | null;
  responses?: ComplaintResponse[];
  created_at: string;
  updated_at: string;
}

export interface PatientSatisfactionSurvey {
  id: number;
  patient_id: number;
  prestation_type: string | null;
  prestation_id: number | null;
  service: string | null;
  note: number;
  commentaire: string | null;
  date: string;
  created_at: string;
}

export type TeleconsultationStatut = "planifiee" | "en_cours" | "terminee" | "annulee";

export interface Teleconsultation {
  id: number;
  structure_id: number;
  site_id: number;
  appointment_id: number;
  patient_id: number;
  practitioner_id: number;
  consultation_id: number | null;
  statut: TeleconsultationStatut;
  lien_session: string | null;
  started_at: string | null;
  ended_at: string | null;
  created_at: string;
  updated_at: string;
}

export type ReferralStatut = "envoye" | "accepte" | "refuse" | "complete";

export interface PatientReferral {
  id: number;
  structure_origine_id: number;
  site_origine_id: number | null;
  structure_destination_id: number;
  patient_id: number;
  praticien_referent_id: number;
  motif: string;
  statut: ReferralStatut;
  compte_rendu_retour: string | null;
  created_at: string;
  updated_at: string;
}

export interface ReferralPatientResume {
  nom: string;
  date_naissance: string;
  numero_patient: string;
}

export interface StructureDirectoryEntry {
  id: number;
  code: string;
  legal_name: string;
  trade_name: string | null;
  city: string | null;
  is_active: boolean;
}

/**
 * `GET /audit-logs` renvoie chaque entrée déjà mappée via `->through()` dans
 * AuditLogController — pas une Resource. `properties` suit le comportement
 * par défaut de spatie/laravel-activitylog (`logOnlyDirty()->logFillable()`) :
 * seulement `{attributes}` à la création, `{attributes, old}` en modification.
 * Traité comme un objet libre, jamais une forme plus précise. `causer_id`
 * est `null` pour une action système (pas d'utilisateur authentifié).
 */
export interface AuditLogEntry {
  id: number;
  log_name: string | null;
  description: string | null;
  event: string | null;
  subject_type: string | null;
  subject_id: number | null;
  causer_id: number | null;
  ip_address: string | null;
  properties: Record<string, unknown> | null;
  created_at: string;
}

/**
 * Forme du paginator Laravel brut (`->paginate()` sérialisé sans Resource) —
 * volontairement distincte de `Paginated<T>` ci-dessus (forme `{data, meta,
 * links}` des endpoints ::collection classiques). Ne pas réutiliser
 * `Paginated<T>` ici : les deux formes JSON ne sont pas interchangeables.
 */
export interface AuditLogPage {
  current_page: number;
  data: AuditLogEntry[];
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}

export type NotificationCanal = "email" | "sms" | "whatsapp" | "push";

export interface NotificationTemplate {
  id: number;
  structure_id: number | null;
  type_evenement: string;
  canal: NotificationCanal;
  sujet: string | null;
  contenu: string;
  actif: boolean;
  created_at: string;
  updated_at: string;
}

/**
 * Compte de l'administrateur de plateforme (guard `platform`) — distinct de
 * AuthenticatedUser/UserAccount (guard `sanctum`), pas de structure_id, pas
 * de rôles/permissions Spatie : un seul type d'acteur possible sur ce guard.
 */
export interface PlatformAdmin {
  id: number;
  name: string;
  email: string;
}

export type StructureType =
  | "cabinet"
  | "centre_specialise"
  | "laboratoire"
  | "imagerie"
  | "clinique"
  | "polyclinique"
  | "groupe_sante";

/** Forme complète de StructureResource — gérée exclusivement par l'espace plateforme. */
export interface Structure {
  id: number;
  code: string;
  legal_name: string;
  trade_name: string | null;
  type: StructureType;
  logo_path: string | null;
  address: string | null;
  city: string | null;
  country: string | null;
  phone: string | null;
  email: string | null;
  opening_hours: Record<string, unknown> | null;
  registration_number: string | null;
  tax_number: string | null;
  color_primary: string | null;
  color_secondary: string | null;
  currency: string | null;
  locale: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface StructureModule {
  id: number;
  structure_id: number;
  module: string;
  is_active: boolean;
  activated_at: string | null;
  deactivated_at: string | null;
}

/**
 * Forme exacte de UserResource telle que renvoyée par
 * PlatformStructureController::store() : `roles`/`sites` proviennent de
 * relations non chargées (`whenLoaded`) et sont donc absentes du JSON — ne
 * pas réutiliser AuthenticatedUser ici, qui les déclare requises.
 */
export interface PlatformCreatedAdminAccount {
  id: number;
  structure_id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone: string | null;
  photo_path: string | null;
  is_active: boolean;
  must_change_password: boolean;
  last_login_at: string | null;
  two_factor_enabled: boolean;
  two_factor_required: boolean;
  roles: string[];
  permissions: string[];
  created_at: string;
  updated_at: string;
}

/**
 * Réponse de POST /platform/structures : `admin_generated_password` n'existe
 * que dans cette réponse — aucun endpoint ne le restitue ensuite. Ne jamais
 * persister ce champ au-delà de l'état local du composant qui l'affiche.
 */
export interface CreateStructureResponse {
  structure: Structure;
  admin: PlatformCreatedAdminAccount;
  admin_generated_password: string;
  message: string;
}

/**
 * `GET /platform/audit-logs` : même patron que AuditLogEntry/AuditLogPage
 * mais sans `log_name`/`event` (toujours 'administration_plateforme', déjà
 * filtré côté serveur) et avec `structure_id` (la structure concernée par
 * l'action, pas celle de l'acteur — PlatformAdmin n'en a pas).
 */
export interface PlatformAuditLogEntry {
  id: number;
  description: string | null;
  structure_id: number | null;
  subject_type: string | null;
  subject_id: number | null;
  causer_id: number | null;
  ip_address: string | null;
  properties: Record<string, unknown> | null;
  created_at: string;
}

export interface PlatformAuditLogPage {
  current_page: number;
  data: PlatformAuditLogEntry[];
  last_page: number;
  per_page: number;
  total: number;
  from: number | null;
  to: number | null;
}
