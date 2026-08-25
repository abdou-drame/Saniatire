/** Mirrors the JSON shapes of the corresponding Laravel API Resources exactly. */

export type SpecialtyType =
  | "maternite"
  | "dentaire"
  | "dialyse"
  | "ophtalmo"
  | "cardiologie"
  | "kinesitherapie"
  | "oncologie"
  | "pma"
  | "sante_mentale"
  | "pediatrie"
  | "medecine_travail"
  | "soins_domicile";

// ---------------------------------------------------------------------------
// Maternité
// ---------------------------------------------------------------------------

export type MaternityStatus = "en_suivi" | "accouchee";

export interface MaternityPrenatalVisit {
  id: number;
  maternity_record_id: number;
  practitioner_id: number;
  visit_number: number;
  gestational_age_weeks: number;
  weight_kg: number | null;
  blood_pressure_systolic: number | null;
  blood_pressure_diastolic: number | null;
  fundal_height_cm: number | null;
  fetal_movements: string | null;
  fetal_heart_rate: number | null;
  visit_date: string;
  notes: string | null;
  created_at: string;
}

export interface MaternityPartogramReading {
  id: number;
  maternity_partogram_id: number;
  recorded_at: string;
  cervical_dilation_cm: number;
  fetal_heart_rate: number | null;
  contractions_per_10min: number | null;
  notes: string | null;
}

export type PartogramStatus = "en_cours" | "termine";

export interface MaternityPartogram {
  id: number;
  maternity_record_id: number;
  labor_started_at: string;
  status: PartogramStatus;
  readings: MaternityPartogramReading[];
  created_at: string;
}

export type DeliveryMode = "voie_basse" | "cesarienne";

export interface MaternityNewborn {
  id: number;
  maternity_delivery_id: number;
  sex: "m" | "f";
  birth_weight_grams: number;
  apgar_1min: number;
  apgar_5min: number;
  apgar_10min: number | null;
}

export interface MaternityDelivery {
  id: number;
  maternity_record_id: number;
  practitioner_id: number;
  mode: DeliveryMode;
  delivered_at: string;
  complications: string | null;
  newborns: MaternityNewborn[];
  created_at: string;
}

export type BleedingStatus = "normal" | "anormal";

export interface MaternityPostpartumVisit {
  id: number;
  maternity_record_id: number;
  practitioner_id: number;
  visit_date: string;
  blood_pressure_systolic: number | null;
  blood_pressure_diastolic: number | null;
  temperature_c: number | null;
  bleeding_status: BleedingStatus | null;
  breastfeeding_status: string | null;
  notes: string | null;
  created_at: string;
}

export interface MaternityRecord {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  consultation_id: number | null;
  last_menstrual_period_date: string;
  estimated_delivery_date: string | null;
  status: MaternityStatus;
  prenatal_visits: MaternityPrenatalVisit[];
  partogram: MaternityPartogram | null;
  delivery: MaternityDelivery | null;
  postpartum_visits: MaternityPostpartumVisit[];
  created_at: string;
  updated_at: string;
}

// ---------------------------------------------------------------------------
// Dentaire
// ---------------------------------------------------------------------------

export type ToothStatus =
  | "saine"
  | "cariee"
  | "obturee"
  | "extraite"
  | "couronnee"
  | "absente"
  | "implant"
  | "bridge";

export interface DentalToothState {
  id: number;
  dental_chart_id: number;
  tooth_fdi: string;
  status: ToothStatus;
  notes: string | null;
  updated_at: string;
}

export interface DentalProcedure {
  id: number;
  dental_chart_id: number;
  consultation_id: number | null;
  practitioner_id: number;
  tooth_fdi: string | null;
  act_type: string;
  performed_at: string;
  notes: string | null;
  created_at: string;
}

export type TreatmentPlanItemStatus = "prevu" | "realise" | "annule";

export interface DentalTreatmentPlanItem {
  id: number;
  dental_treatment_plan_id: number;
  tooth_fdi: string | null;
  act_type: string;
  status: TreatmentPlanItemStatus;
  planned_at: string | null;
}

export type TreatmentPlanStatus = "en_cours" | "termine" | "annule";

export interface DentalTreatmentPlan {
  id: number;
  dental_chart_id: number;
  created_by: number;
  status: TreatmentPlanStatus;
  items: DentalTreatmentPlanItem[];
  created_at: string;
}

export interface DentalChart {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  consultation_id: number | null;
  tooth_states: DentalToothState[];
  procedures: DentalProcedure[];
  treatment_plans: DentalTreatmentPlan[];
  created_at: string;
  updated_at: string;
}

// ---------------------------------------------------------------------------
// Dialyse
// ---------------------------------------------------------------------------

export type VascularAccessType = "fistule" | "catheter" | "greffon";
export type VascularAccessStatus = "fonctionnel" | "complique";
export type DialysisProgramStatus = "actif" | "suspendu" | "arrete";

export interface DialysisSessionVital {
  id: number;
  dialysis_session_id: number;
  measured_at: string;
  blood_pressure_systolic: number;
  blood_pressure_diastolic: number;
  heart_rate: number;
}

export type DialysisSessionStatus = "terminee" | "interrompue";

export interface DialysisSession {
  id: number;
  dialysis_program_id: number;
  practitioner_id: number;
  session_date: string;
  pre_weight_kg: number;
  post_weight_kg: number | null;
  dry_weight_kg: number | null;
  duration_minutes: number | null;
  blood_flow_rate_ml_min: number | null;
  ultrafiltration_volume_ml: number | null;
  complications: string | null;
  status: DialysisSessionStatus;
  vitals: DialysisSessionVital[];
  created_at: string;
}

export interface DialysisProgram {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  consultation_id: number | null;
  frequency_per_week: number;
  dry_weight_kg: number;
  vascular_access_type: VascularAccessType;
  vascular_access_status: VascularAccessStatus | null;
  status: DialysisProgramStatus;
  started_at: string;
  sessions: DialysisSession[];
  created_at: string;
  updated_at: string;
}

// ---------------------------------------------------------------------------
// Ophtalmologie — flat repeatable exam record, no children.
// ---------------------------------------------------------------------------

export interface OphtalmoRecord {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  consultation_id: number | null;
  visual_acuity_od_uncorrected: string | null;
  visual_acuity_od_corrected: string | null;
  visual_acuity_og_uncorrected: string | null;
  visual_acuity_og_corrected: string | null;
  intraocular_pressure_od: number | null;
  intraocular_pressure_og: number | null;
  refraction_od_sphere: number | null;
  refraction_od_cylinder: number | null;
  refraction_od_axis: number | null;
  refraction_og_sphere: number | null;
  refraction_og_cylinder: number | null;
  refraction_og_axis: number | null;
  fundus_exam: string | null;
  optical_correction_prescription: string | null;
  examined_at: string;
  created_at: string;
  updated_at: string;
}

// ---------------------------------------------------------------------------
// Cardiologie
// ---------------------------------------------------------------------------

export type CardioRhythm = "regulier" | "irregulier";

export interface CardioReading {
  id: number;
  cardio_record_id: number;
  measured_at: string;
  blood_pressure_systolic: number;
  blood_pressure_diastolic: number;
  heart_rate: number;
  rhythm: CardioRhythm;
}

export interface CardioEcgResult {
  id: number;
  cardio_record_id: number;
  performed_at: string;
  rhythm: string;
  heart_rate: number | null;
  anomalies: string | null;
  tracing_file_reference: string | null;
}

export interface CardioRecord {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  consultation_id: number | null;
  risk_factors: string[] | null;
  current_treatment: string | null;
  examined_at: string;
  readings: CardioReading[];
  ecg_results: CardioEcgResult[];
  created_at: string;
  updated_at: string;
}

// ---------------------------------------------------------------------------
// Kinésithérapie
// ---------------------------------------------------------------------------

export type KineProgramStatus = "actif" | "termine";

export interface KineSession {
  id: number;
  kine_program_id: number;
  practitioner_id: number;
  session_date: string;
  exercises_performed: string | null;
  evolution: string | null;
  pain_scale: number | null;
  observations: string | null;
  created_at: string;
}

export interface KineProgram {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  consultation_id: number | null;
  affected_area: string;
  initial_range_of_motion: string | null;
  initial_pain_scale: number | null;
  objectives: string | null;
  status: KineProgramStatus;
  started_at: string;
  sessions: KineSession[];
  created_at: string;
  updated_at: string;
}

// ---------------------------------------------------------------------------
// Oncologie
// ---------------------------------------------------------------------------

export type OncoStageT = "Tis" | "T0" | "T1" | "T2" | "T3" | "T4";
export type OncoStageN = "N0" | "N1" | "N2" | "N3";
export type OncoStageM = "M0" | "M1";
export type OncoResponse = "reponse_complete" | "reponse_partielle" | "stable" | "progression";

export interface OncoChemoCycle {
  id: number;
  onco_record_id: number;
  cycle_number: number;
  cycle_date: string;
  medications: string | null;
  side_effects: string | null;
}

export interface OncoResponseEvaluation {
  id: number;
  onco_record_id: number;
  evaluated_at: string;
  response: OncoResponse;
  notes: string | null;
}

export interface OncoRecord {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  consultation_id: number | null;
  cancer_type: string;
  stage_t: OncoStageT | null;
  stage_n: OncoStageN | null;
  stage_m: OncoStageM | null;
  protocol_name: string | null;
  treatment_line: number | null;
  diagnosed_at: string | null;
  chemo_cycles: OncoChemoCycle[];
  response_evaluations: OncoResponseEvaluation[];
  created_at: string;
  updated_at: string;
}

// ---------------------------------------------------------------------------
// PMA / Fertilité — confidentialité renforcée (accès restreint côté backend
// ET côté menu frontend, cf. specialty-registry.tsx).
// ---------------------------------------------------------------------------

export type PmaAttemptResult = "en_cours" | "positif" | "negatif";

export interface PmaStimulationProtocol {
  id: number;
  pma_record_id: number;
  protocol_type: string;
  medications: string | null;
  started_at: string;
  ended_at: string | null;
}

export interface PmaCycleMonitoring {
  id: number;
  pma_record_id: number;
  monitoring_date: string;
  echo_observations: string | null;
  hormone_level: number | null;
  puncture_date: string | null;
  transfer_date: string | null;
}

export interface PmaRecord {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  consultation_id: number | null;
  fertility_history: string | null;
  exams_performed: string | null;
  attempt_result: PmaAttemptResult;
  stimulation_protocols: PmaStimulationProtocol[];
  cycle_monitorings: PmaCycleMonitoring[];
  created_at: string;
  updated_at: string;
}

// ---------------------------------------------------------------------------
// Santé mentale — confidentialité renforcée (même remarque que PMA).
// ---------------------------------------------------------------------------

export interface MentalHealthScaleScore {
  id: number;
  mental_health_record_id: number;
  scale_name: string;
  score: number;
  scored_at: string;
}

export interface MentalHealthRecord {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  consultation_id: number | null;
  consultation_reason: string | null;
  clinical_evaluation: string | null;
  ongoing_treatment: string | null;
  scale_scores: MentalHealthScaleScore[];
  created_at: string;
  updated_at: string;
}

// ---------------------------------------------------------------------------
// Pédiatrie
// ---------------------------------------------------------------------------

export interface PediatricGrowthMeasurement {
  id: number;
  pediatric_record_id: number;
  measured_at: string;
  weight_kg: number | null;
  height_cm: number | null;
  head_circumference_cm: number | null;
}

export interface PediatricVaccination {
  id: number;
  pediatric_record_id: number;
  vaccine_name: string;
  dose_number: number | null;
  administered_at: string;
}

export interface PediatricDevelopmentObservation {
  id: number;
  pediatric_record_id: number;
  age_months: number;
  observation: string;
  observed_at: string;
}

export interface PediatricRecord {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  consultation_id: number | null;
  growth_measurements: PediatricGrowthMeasurement[];
  vaccinations: PediatricVaccination[];
  development_observations: PediatricDevelopmentObservation[];
  created_at: string;
  updated_at: string;
}

// ---------------------------------------------------------------------------
// Médecine du travail — flat repeatable visit record, no children.
// ---------------------------------------------------------------------------

export type OccupationalVisitType = "embauche" | "periodique" | "reprise" | "demande";
export type OccupationalFitnessStatus = "apte" | "apte_avec_reserves" | "inapte";

export interface OccupationalHealthRecord {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  consultation_id: number | null;
  visit_type: OccupationalVisitType;
  fitness_status: OccupationalFitnessStatus;
  restrictions: string | null;
  risk_exposures: string[] | null;
  visit_date: string;
  next_visit_due_at: string | null;
  created_at: string;
  updated_at: string;
}

// ---------------------------------------------------------------------------
// Soins à domicile
// ---------------------------------------------------------------------------

export interface HomeCareVisit {
  id: number;
  home_care_record_id: number;
  intervenant_id: number;
  care_type: string;
  visit_datetime: string;
  report: string | null;
}

export interface HomeCareRecord {
  id: number;
  structure_id: number;
  site_id: number;
  patient_id: number;
  consultation_id: number | null;
  intervention_address: string;
  care_type: string;
  visits: HomeCareVisit[];
  created_at: string;
  updated_at: string;
}
