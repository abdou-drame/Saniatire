<?php

use App\Domain\Dentaire\Support\FdiNumbering;
use App\Http\Controllers\Api\AiAssistanceController;
use App\Http\Controllers\Api\ApprovalRuleController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BedController;
use App\Http\Controllers\Api\BillableItemController;
use App\Http\Controllers\Api\BiomedicalEquipmentController;
use App\Http\Controllers\Api\CardioRecordController;
use App\Http\Controllers\Api\CashSessionController;
use App\Http\Controllers\Api\ConsultationController;
use App\Http\Controllers\Api\ComplaintController;
use App\Http\Controllers\Api\CreancesController;
use App\Http\Controllers\Api\DashboardDirectionController;
use App\Http\Controllers\Api\DashboardFinancierController;
use App\Http\Controllers\Api\DashboardMedicalController;
use App\Http\Controllers\Api\DashboardQualiteController;
use App\Http\Controllers\Api\DentalChartController;
use App\Http\Controllers\Api\DialysisProgramController;
use App\Http\Controllers\Api\EmployeeProfileController;
use App\Http\Controllers\Api\EquipmentMaintenanceController;
use App\Http\Controllers\Api\ExternalPrescriberController;
use App\Http\Controllers\Api\Fhir\ConditionController as FhirConditionController;
use App\Http\Controllers\Api\Fhir\DiagnosticReportController as FhirDiagnosticReportController;
use App\Http\Controllers\Api\Fhir\EncounterController as FhirEncounterController;
use App\Http\Controllers\Api\Fhir\ObservationController as FhirObservationController;
use App\Http\Controllers\Api\Fhir\PatientController as FhirPatientController;
use App\Http\Controllers\Api\Fhir\ServiceRequestController as FhirServiceRequestController;
use App\Http\Controllers\Api\HomeCareRecordController;
use App\Http\Controllers\Api\HospitalizationController;
use App\Http\Controllers\Api\IcdCodeController;
use App\Http\Controllers\Api\ImagingOrderController;
use App\Http\Controllers\Api\ImagingReportController;
use App\Http\Controllers\Api\ImagingStudyController;
use App\Http\Controllers\Api\InsuranceConventionController;
use App\Http\Controllers\Api\InsuranceConventionCoverageRuleController;
use App\Http\Controllers\Api\InsuranceProviderController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\KineProgramController;
use App\Http\Controllers\Api\LabOrderController;
use App\Http\Controllers\Api\LabResultController;
use App\Http\Controllers\Api\LabSampleController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\LoincCodeController;
use App\Http\Controllers\Api\MaternityRecordController;
use App\Http\Controllers\Api\MentalHealthRecordController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\NotificationTemplateController;
use App\Http\Controllers\Api\OccupationalHealthRecordController;
use App\Http\Controllers\Api\OnCallController;
use App\Http\Controllers\Api\OncoRecordController;
use App\Http\Controllers\Api\OphtalmoRecordController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PatientInsuranceCoverageController;
use App\Http\Controllers\Api\PatientPortalAuthController;
use App\Http\Controllers\Api\PatientPortalController;
use App\Http\Controllers\Api\PatientReferralController;
use App\Http\Controllers\Api\PatientSatisfactionSurveyController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PediatricRecordController;
use App\Http\Controllers\Api\PmaRecordController;
use App\Http\Controllers\Api\PractitionerController;
use App\Http\Controllers\Api\PrescriberPortalAuthController;
use App\Http\Controllers\Api\PrescriberPortalController;
use App\Http\Controllers\Api\ProductBatchController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\PurchaseOrderController;
use App\Http\Controllers\Api\PurchaseOrderReceptionController;
use App\Http\Controllers\Api\PurchaseRequestController;
use App\Http\Controllers\Api\QueueEntryController;
use App\Http\Controllers\Api\QuoteController;
use App\Http\Controllers\Api\ReportExportController;
use App\Http\Controllers\Api\ServiceTariffController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\StockAlertController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\StockThresholdController;
use App\Http\Controllers\Api\StructureController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\SurgicalProcedureController;
use App\Http\Controllers\Api\TeleconsultationController;
use App\Http\Controllers\Api\TwoFactorController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VoiceDictationController;
use App\Http\Controllers\Api\WardController;
use App\Http\Controllers\Api\WorkScheduleController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// Étape 9 §2 : échange d'un challenge 2FA temporaire contre un vrai token —
// volontairement sans auth:sanctum (l'utilisateur n'a justement pas encore
// de token à ce stade), voir AuthController::login()/TwoFactorController.
Route::post('/auth/2fa/challenge', [TwoFactorController::class, 'challenge']);

// --- Étape 7b : portail patient (guard `patient`) ---

Route::post('/portail-patient/activer', [PatientPortalAuthController::class, 'activate']);
Route::post('/portail-patient/login', [PatientPortalAuthController::class, 'login']);
Route::post('/portail-patient/mot-de-passe-oublie', [PatientPortalAuthController::class, 'forgotPassword']);
Route::post('/portail-patient/reinitialiser-mot-de-passe', [PatientPortalAuthController::class, 'resetPassword']);

Route::middleware(['auth:patient', 'tenant:patient'])->prefix('portail-patient')->group(function () {
    Route::post('/logout', [PatientPortalAuthController::class, 'logout']);
    Route::get('/me', [PatientPortalAuthController::class, 'me']);

    Route::get('/sites', [PatientPortalController::class, 'sites']);
    Route::get('/practitioners', [PatientPortalController::class, 'practitioners']);

    Route::get('/rendez-vous', [PatientPortalController::class, 'appointments']);
    Route::get('/creneaux-disponibles', [PatientPortalController::class, 'creneauxDisponibles']);
    Route::post('/rendez-vous', [PatientPortalController::class, 'storeAppointment']);

    Route::get('/factures', [PatientPortalController::class, 'invoices']);
    Route::get('/factures/{invoice}', [PatientPortalController::class, 'invoice']);
    Route::get('/solde', [PatientPortalController::class, 'solde']);
    Route::get('/documents', [PatientPortalController::class, 'documents']);

    Route::get('/preferences-notification', [NotificationPreferenceController::class, 'show']);
    Route::put('/preferences-notification', [NotificationPreferenceController::class, 'update']);
});

// --- Étape 7b : portail prescripteur externe (guard `prescriber`) ---

Route::post('/portail-prescripteur/activer', [PrescriberPortalAuthController::class, 'activate']);
Route::post('/portail-prescripteur/login', [PrescriberPortalAuthController::class, 'login']);
Route::post('/portail-prescripteur/mot-de-passe-oublie', [PrescriberPortalAuthController::class, 'forgotPassword']);
Route::post('/portail-prescripteur/reinitialiser-mot-de-passe', [PrescriberPortalAuthController::class, 'resetPassword']);

Route::middleware(['auth:prescriber', 'tenant:prescriber'])->prefix('portail-prescripteur')->group(function () {
    Route::post('/logout', [PrescriberPortalAuthController::class, 'logout']);
    Route::get('/me', [PrescriberPortalAuthController::class, 'me']);

    Route::get('/sites', [PrescriberPortalController::class, 'sites']);
    Route::get('/loinc-codes', [PrescriberPortalController::class, 'loincCodes']);
    Route::get('/patients', [PrescriberPortalController::class, 'patients']);

    Route::post('/demandes-labo', [PrescriberPortalController::class, 'storeDemandeLabo']);
    Route::get('/demandes-labo', [PrescriberPortalController::class, 'demandesLabo']);
    Route::post('/demandes-imagerie', [PrescriberPortalController::class, 'storeDemandeImagerie']);
    Route::get('/demandes-imagerie', [PrescriberPortalController::class, 'demandesImagerie']);
});

// Étape 9 §2 : ce groupe reste volontairement hors du middleware
// `two_factor` — /auth/logout, /auth/me et /auth/2fa/* doivent rester
// accessibles à un utilisateur dont la 2FA obligatoire n'est pas encore
// confirmée, sinon il ne pourrait jamais l'activer. Voir
// EnsureTwoFactorSetupComplete pour le détail du blocage appliqué au
// groupe suivant.
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::post('/auth/2fa/setup', [TwoFactorController::class, 'setup']);
    Route::post('/auth/2fa/confirm', [TwoFactorController::class, 'confirm']);
    Route::post('/auth/2fa/disable', [TwoFactorController::class, 'disable']);
});

Route::middleware(['auth:sanctum', 'tenant', 'two_factor'])->group(function () {
    Route::apiResource('structures', StructureController::class);
    Route::apiResource('sites', SiteController::class);
    Route::apiResource('users', UserController::class);

    Route::apiResource('patients', PatientController::class);
    Route::get('/patients/{patient}/timeline', [PatientController::class, 'timeline']);
    Route::post('/patients/{patient}/portal/send-activation', [PatientController::class, 'sendPortalActivation']);

    Route::apiResource('appointments', AppointmentController::class)->except(['destroy']);
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::get('/practitioners', [PractitionerController::class, 'index']);

    Route::apiResource('queue-entries', QueueEntryController::class)->only(['index', 'store']);
    Route::patch('/queue-entries/{queueEntry}/status', [QueueEntryController::class, 'updateStatus']);
    Route::get('/queue-entries-stats', [QueueEntryController::class, 'stats']);

    Route::apiResource('consultations', ConsultationController::class)->except(['destroy']);
    Route::post('/consultations/{consultation}/close', [ConsultationController::class, 'close']);
    Route::post('/consultations/{consultation}/diagnoses', [ConsultationController::class, 'storeDiagnosis']);
    Route::patch('/consultations/{consultation}/diagnoses/{diagnosis}', [ConsultationController::class, 'updateDiagnosis']);

    Route::get('/icd-codes', [IcdCodeController::class, 'index']);
    Route::get('/icd-codes/stats', [IcdCodeController::class, 'stats']);
    Route::get('/icd-codes/{icdCode}', [IcdCodeController::class, 'show']);
    Route::get('/icd-codes/{icdCode}/children', [IcdCodeController::class, 'children']);
    Route::get('/icd-codes/{icdCode}/equivalents', [IcdCodeController::class, 'equivalents']);

    Route::get('/loinc-codes', [LoincCodeController::class, 'index']);
    Route::get('/loinc-codes/{loincCode}', [LoincCodeController::class, 'show']);

    Route::get('/lab-orders/stats', [LabOrderController::class, 'stats']);
    Route::apiResource('lab-orders', LabOrderController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('/lab-orders/{labOrder}/cancel', [LabOrderController::class, 'cancel']);
    Route::post('/lab-orders/{labOrder}/samples', [LabSampleController::class, 'store']);
    Route::post('/lab-samples/{labSample}/results', [LabResultController::class, 'store']);
    Route::patch('/lab-results/{labResult}/validate-technique', [LabResultController::class, 'validateTechnique']);
    Route::patch('/lab-results/{labResult}/validate-biologique', [LabResultController::class, 'validateBiologique']);
    Route::patch('/lab-results/{labResult}/transmit', [LabResultController::class, 'transmit']);

    Route::get('/imaging-orders/stats', [ImagingOrderController::class, 'stats']);
    Route::apiResource('imaging-orders', ImagingOrderController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('/imaging-orders/{imagingOrder}/cancel', [ImagingOrderController::class, 'cancel']);
    Route::post('/imaging-orders/{imagingOrder}/studies', [ImagingStudyController::class, 'store']);
    Route::patch('/imaging-studies/{imagingStudy}/transmit', [ImagingStudyController::class, 'transmit']);
    Route::post('/imaging-studies/{imagingStudy}/report', [ImagingReportController::class, 'store']);
    Route::patch('/imaging-reports/{imagingReport}/validate', [ImagingReportController::class, 'validateReport']);

    Route::get('/wards/occupancy-stats', [WardController::class, 'occupancyStats']);
    Route::apiResource('wards', WardController::class)->only(['index', 'store', 'show', 'update']);
    Route::apiResource('beds', BedController::class)->only(['index', 'store', 'show', 'update']);

    Route::get('/hospitalizations/stats', [HospitalizationController::class, 'stats']);
    Route::apiResource('hospitalizations', HospitalizationController::class)->only(['index', 'store', 'show']);
    Route::post('/hospitalizations/{hospitalization}/discharge', [HospitalizationController::class, 'discharge']);
    Route::post('/hospitalizations/{hospitalization}/daily-notes', [HospitalizationController::class, 'storeDailyNote']);

    Route::apiResource('surgical-procedures', SurgicalProcedureController::class)->only(['index', 'store', 'show']);
    Route::post('/surgical-procedures/{surgicalProcedure}/start', [SurgicalProcedureController::class, 'start']);
    Route::post('/surgical-procedures/{surgicalProcedure}/complete', [SurgicalProcedureController::class, 'complete']);
    Route::post('/surgical-procedures/{surgicalProcedure}/cancel', [SurgicalProcedureController::class, 'cancel']);
    Route::post('/surgical-procedures/{surgicalProcedure}/checklist/{step}', [SurgicalProcedureController::class, 'submitChecklistStep'])
        ->whereIn('step', ['avant_anesthesie', 'avant_incision', 'avant_sortie_bloc']);

    // --- Étape 4a : spécialités (architecture générique validée sur
    // maternité, dentaire, dialyse — voir App\Domain\Shared\Specialty) ---

    Route::get('/maternite-stats', [MaternityRecordController::class, 'stats']);
    Route::apiResource('maternity-records', MaternityRecordController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('/maternity-records/{maternityRecord}/prenatal-visits', [MaternityRecordController::class, 'storePrenatalVisit']);
    Route::post('/maternity-records/{maternityRecord}/partogram', [MaternityRecordController::class, 'storePartogram']);
    Route::post('/maternity-partograms/{partogram}/readings', [MaternityRecordController::class, 'storePartogramReading']);
    Route::post('/maternity-records/{maternityRecord}/delivery', [MaternityRecordController::class, 'storeDelivery']);
    Route::post('/maternity-deliveries/{delivery}/newborns', [MaternityRecordController::class, 'storeNewborn']);
    Route::post('/maternity-records/{maternityRecord}/postpartum-visits', [MaternityRecordController::class, 'storePostpartumVisit']);

    Route::get('/dentaire-stats', [DentalChartController::class, 'stats']);
    Route::apiResource('dental-charts', DentalChartController::class)->only(['index', 'store', 'show']);
    Route::put('/dental-charts/{dentalChart}/teeth/{fdi}', [DentalChartController::class, 'updateToothState'])
        ->whereIn('fdi', FdiNumbering::validCodes());
    Route::post('/dental-charts/{dentalChart}/procedures', [DentalChartController::class, 'storeProcedure']);
    Route::post('/dental-charts/{dentalChart}/treatment-plans', [DentalChartController::class, 'storeTreatmentPlan']);
    Route::post('/dental-treatment-plans/{treatmentPlan}/items', [DentalChartController::class, 'storeTreatmentPlanItem']);
    Route::patch('/dental-treatment-plan-items/{item}', [DentalChartController::class, 'updateTreatmentPlanItem']);

    Route::get('/dialyse-stats', [DialysisProgramController::class, 'stats']);
    Route::apiResource('dialysis-programs', DialysisProgramController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('/dialysis-programs/{dialysisProgram}/sessions', [DialysisProgramController::class, 'storeSession']);
    Route::post('/dialysis-sessions/{session}/vitals', [DialysisProgramController::class, 'storeSessionVital']);

    // --- Étape 4b : spécialités restantes (même architecture générique) ---

    Route::get('/ophtalmo-stats', [OphtalmoRecordController::class, 'stats']);
    Route::apiResource('ophtalmo-records', OphtalmoRecordController::class)->only(['index', 'store', 'show', 'update']);

    Route::get('/cardiologie-stats', [CardioRecordController::class, 'stats']);
    Route::apiResource('cardio-records', CardioRecordController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('/cardio-records/{cardioRecord}/readings', [CardioRecordController::class, 'storeReading']);
    Route::post('/cardio-records/{cardioRecord}/ecg-results', [CardioRecordController::class, 'storeEcgResult']);

    Route::get('/kinesitherapie-stats', [KineProgramController::class, 'stats']);
    Route::apiResource('kine-programs', KineProgramController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('/kine-programs/{kineProgram}/sessions', [KineProgramController::class, 'storeSession']);

    Route::get('/oncologie-stats', [OncoRecordController::class, 'stats']);
    Route::apiResource('onco-records', OncoRecordController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('/onco-records/{oncoRecord}/chemo-cycles', [OncoRecordController::class, 'storeChemoCycle']);
    Route::post('/onco-records/{oncoRecord}/response-evaluations', [OncoRecordController::class, 'storeResponseEvaluation']);

    Route::get('/pma-stats', [PmaRecordController::class, 'stats']);
    Route::apiResource('pma-records', PmaRecordController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('/pma-records/{pmaRecord}/stimulation-protocols', [PmaRecordController::class, 'storeStimulationProtocol']);
    Route::post('/pma-records/{pmaRecord}/cycle-monitorings', [PmaRecordController::class, 'storeCycleMonitoring']);

    Route::get('/sante-mentale-stats', [MentalHealthRecordController::class, 'stats']);
    Route::apiResource('mental-health-records', MentalHealthRecordController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('/mental-health-records/{mentalHealthRecord}/scale-scores', [MentalHealthRecordController::class, 'storeScaleScore']);

    Route::get('/pediatrie-stats', [PediatricRecordController::class, 'stats']);
    Route::apiResource('pediatric-records', PediatricRecordController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('/pediatric-records/{pediatricRecord}/growth-measurements', [PediatricRecordController::class, 'storeGrowthMeasurement']);
    Route::post('/pediatric-records/{pediatricRecord}/vaccinations', [PediatricRecordController::class, 'storeVaccination']);
    Route::post('/pediatric-records/{pediatricRecord}/development-observations', [PediatricRecordController::class, 'storeDevelopmentObservation']);

    Route::get('/medecine-travail-stats', [OccupationalHealthRecordController::class, 'stats']);
    Route::apiResource('occupational-health-records', OccupationalHealthRecordController::class)->only(['index', 'store', 'show', 'update']);

    Route::get('/soins-domicile-stats', [HomeCareRecordController::class, 'stats']);
    Route::apiResource('home-care-records', HomeCareRecordController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('/home-care-records/{homeCareRecord}/visits', [HomeCareRecordController::class, 'storeVisit']);

    // --- Étape 5a : pharmacie, stocks, achats, équipements biomédicaux ---

    Route::apiResource('products', ProductController::class);
    Route::apiResource('product-batches', ProductBatchController::class)->only(['index', 'store', 'show', 'update']);
    Route::apiResource('stock-thresholds', StockThresholdController::class);
    Route::get('/stock/alerts/low-threshold', [StockAlertController::class, 'lowThreshold']);
    Route::get('/stock/alerts/expiry', [StockAlertController::class, 'expiry']);
    Route::apiResource('stock-movements', StockMovementController::class)->only(['index', 'store']);

    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('purchase-requests', PurchaseRequestController::class)->only(['index', 'store', 'show']);
    Route::post('/purchase-requests/{purchaseRequest}/approve', [PurchaseRequestController::class, 'approve']);
    Route::post('/purchase-requests/{purchaseRequest}/reject', [PurchaseRequestController::class, 'reject']);

    Route::apiResource('approval-rules', ApprovalRuleController::class);

    Route::apiResource('purchase-orders', PurchaseOrderController::class)->only(['index', 'store', 'show']);
    Route::post('/purchase-orders/{purchaseOrder}/submit', [PurchaseOrderController::class, 'submit']);
    Route::post('/purchase-orders/{purchaseOrder}/approve', [PurchaseOrderController::class, 'approve']);
    Route::post('/purchase-orders/{purchaseOrder}/cancel', [PurchaseOrderController::class, 'cancel']);
    Route::post('/purchase-order-items/{purchaseOrderItem}/receptions', [PurchaseOrderReceptionController::class, 'store']);

    Route::get('/equipment-maintenances/upcoming', [EquipmentMaintenanceController::class, 'upcoming']);
    Route::get('/equipment-maintenances/overdue', [EquipmentMaintenanceController::class, 'overdue']);
    Route::apiResource('biomedical-equipment', BiomedicalEquipmentController::class);
    Route::apiResource('equipment-maintenances', EquipmentMaintenanceController::class)->only(['index', 'store', 'show', 'update']);

    // --- Étape 5b : facturation, caisse, assurances, créances ---

    Route::apiResource('service-tariffs', ServiceTariffController::class);
    Route::get('/billable-items', [BillableItemController::class, 'index']);

    Route::apiResource('insurance-providers', InsuranceProviderController::class);
    Route::apiResource('insurance-conventions', InsuranceConventionController::class);
    Route::post('/insurance-conventions/{insuranceConvention}/coverage-rules', [InsuranceConventionController::class, 'storeCoverageRule']);
    Route::patch('/insurance-convention-coverage-rules/{insuranceConventionCoverageRule}', [InsuranceConventionCoverageRuleController::class, 'update']);
    Route::delete('/insurance-convention-coverage-rules/{insuranceConventionCoverageRule}', [InsuranceConventionCoverageRuleController::class, 'destroy']);
    Route::apiResource('patient-insurance-coverages', PatientInsuranceCoverageController::class);

    Route::apiResource('invoices', InvoiceController::class)->only(['index', 'store', 'show']);
    Route::post('/invoices/{invoice}/emit', [InvoiceController::class, 'emit']);
    Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);

    Route::apiResource('quotes', QuoteController::class)->only(['index', 'store', 'show']);
    Route::post('/quotes/{quote}/cancel', [QuoteController::class, 'cancel']);
    Route::post('/quotes/{quote}/convert', [QuoteController::class, 'convert']);

    Route::apiResource('cash-sessions', CashSessionController::class)->only(['index', 'store', 'show']);
    Route::post('/cash-sessions/{cashSession}/close', [CashSessionController::class, 'close']);

    Route::apiResource('payments', PaymentController::class)->only(['index', 'store']);

    Route::get('/creances/balance-agee', [CreancesController::class, 'balanceAgee']);

    // --- Étape 6 : RH (personnel, plannings, gardes/astreintes, congés) ---

    Route::apiResource('employee-profiles', EmployeeProfileController::class)->only(['index', 'store', 'show', 'update']);

    Route::apiResource('work-schedules', WorkScheduleController::class)->only(['index', 'store', 'show', 'update', 'destroy']);
    Route::get('/employees/{user}/planning', [WorkScheduleController::class, 'planning']);

    Route::get('/on-call', [OnCallController::class, 'index']);
    Route::get('/on-call/now', [OnCallController::class, 'now']);

    Route::apiResource('leave-requests', LeaveRequestController::class)->only(['index', 'store', 'show']);
    Route::patch('/leave-requests/{leaveRequest}/validate', [LeaveRequestController::class, 'validateRequest']);
    Route::patch('/leave-requests/{leaveRequest}/refuse', [LeaveRequestController::class, 'refuse']);

    // --- Étape 7a : notifications multicanal ---

    Route::apiResource('notification-templates', NotificationTemplateController::class);

    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'show']);
    Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update']);
    Route::get('/patients/{patient}/notification-preferences', [NotificationPreferenceController::class, 'showForPatient']);
    Route::put('/patients/{patient}/notification-preferences', [NotificationPreferenceController::class, 'updateForPatient']);

    // --- Étape 7b : portails externes (staff-side) ---

    Route::apiResource('external-prescribers', ExternalPrescriberController::class);
    Route::post('/external-prescribers/{externalPrescriber}/portal/send-activation', [ExternalPrescriberController::class, 'sendPortalActivation']);

    Route::apiResource('teleconsultations', TeleconsultationController::class)->only(['index', 'store', 'show']);
    Route::post('/teleconsultations/{teleconsultation}/start', [TeleconsultationController::class, 'start']);
    Route::post('/teleconsultations/{teleconsultation}/close', [TeleconsultationController::class, 'close']);
    Route::post('/teleconsultations/{teleconsultation}/cancel', [TeleconsultationController::class, 'cancel']);

    Route::apiResource('patient-referrals', PatientReferralController::class)
        ->only(['index', 'store', 'show'])
        ->parameters(['patient-referrals' => 'referral']);
    Route::post('/patient-referrals/{referral}/accept', [PatientReferralController::class, 'accept']);
    Route::post('/patient-referrals/{referral}/refuse', [PatientReferralController::class, 'refuse']);
    Route::post('/patient-referrals/{referral}/complete', [PatientReferralController::class, 'completeWithReport']);
    Route::get('/patient-referrals/{referral}/patient-resume', [PatientReferralController::class, 'patientResume']);

    // --- Étape 8 : Tableaux de bord, Reporting, Qualité ---

    Route::get('/dashboards/medical', [DashboardMedicalController::class, 'index']);
    Route::get('/dashboards/financier', [DashboardFinancierController::class, 'index']);
    Route::get('/dashboards/direction', [DashboardDirectionController::class, 'index']);
    Route::get('/dashboards/qualite', [DashboardQualiteController::class, 'index']);

    Route::post('/patient-satisfaction-surveys/send-invitation', [PatientSatisfactionSurveyController::class, 'sendInvitation']);
    Route::apiResource('patient-satisfaction-surveys', PatientSatisfactionSurveyController::class)->only(['index', 'store']);

    Route::apiResource('complaints', ComplaintController::class)->only(['index', 'store', 'show']);
    Route::post('/complaints/{complaint}/assign', [ComplaintController::class, 'assign']);
    Route::post('/complaints/{complaint}/respond', [ComplaintController::class, 'respond']);
    Route::post('/complaints/{complaint}/resolve', [ComplaintController::class, 'resolve']);
    Route::post('/complaints/{complaint}/close', [ComplaintController::class, 'close']);

    Route::get('/reports/epidemiologie/export', [ReportExportController::class, 'epidemiologie']);
    Route::get('/reports/chiffre-affaires/export', [ReportExportController::class, 'chiffreAffaires']);
    Route::get('/reports/balance-agee/export', [ReportExportController::class, 'balanceAgee']);

    // --- Étape 9 §2 : sessions/connexions actives ---

    Route::get('/auth/sessions', [SessionController::class, 'index']);
    Route::delete('/auth/sessions/{tokenId}', [SessionController::class, 'destroy']);
    Route::post('/auth/sessions/revoke-others', [SessionController::class, 'revokeOthers']);

    // --- Étape 9 §3 : audit ---

    Route::get('/audit-logs', [AuditLogController::class, 'index']);

    // --- Étape 9 §4 : assistance IA (jamais persisté automatiquement) ---

    Route::post('/consultations/{consultation}/ai-summary', [AiAssistanceController::class, 'summary']);
    Route::get('/consultations/{consultation}/anomalies', [AiAssistanceController::class, 'anomalies']);

    Route::apiResource('voice-dictations', VoiceDictationController::class)->only(['index', 'store', 'show']);

    // --- Étape 9 §1 : interopérabilité HL7 FHIR R4 (lecture seule) ---

    Route::get('/fhir/Patient', [FhirPatientController::class, 'index']);
    Route::get('/fhir/Patient/{id}', [FhirPatientController::class, 'show']);
    Route::get('/fhir/Encounter', [FhirEncounterController::class, 'index']);
    Route::get('/fhir/Encounter/{id}', [FhirEncounterController::class, 'show']);
    Route::get('/fhir/Condition/{id}', [FhirConditionController::class, 'show']);
    Route::get('/fhir/Observation', [FhirObservationController::class, 'index']);
    Route::get('/fhir/Observation/{id}', [FhirObservationController::class, 'show']);
    Route::get('/fhir/DiagnosticReport/{id}', [FhirDiagnosticReportController::class, 'show']);
    Route::get('/fhir/ServiceRequest/{id}', [FhirServiceRequestController::class, 'show']);
});
