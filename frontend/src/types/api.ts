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
  name: string;
  code?: string;
}

export interface AuthenticatedUser {
  id: number;
  structure_id: number;
  first_name: string;
  last_name: string;
  email: string;
  phone?: string | null;
  is_active: boolean;
  two_factor_enabled: boolean;
  two_factor_required: boolean;
  roles: string[];
  permissions: string[];
  sites: Site[];
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
  insurance_convention_id: number | null;
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

export interface Payment {
  id: number;
  invoice_id: number;
  mode_paiement: string;
  reference_transaction: string | null;
  statut_mobile_money: string | null;
  montant: number;
  numero_recu: string | null;
  paid_at: string | null;
  created_at: string;
}

export interface Invoice {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  insurance_convention_id: number | null;
  numero: string;
  date_emission: string;
  montant_total: number;
  montant_part_patient: number;
  montant_part_assurance: number;
  statut: InvoiceStatus;
  items?: InvoiceItem[];
  payments?: Payment[];
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
  technical_validated_at: string | null;
  biological_validated_by: number | null;
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
  content: string;
  status: ImagingReportStatus;
  validated_at: string | null;
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
