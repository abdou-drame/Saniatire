<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Modules covered so far.
     */
    private const MODULES = [
        'structures', 'sites', 'users', 'patients',
        'appointments', 'queue', 'consultations', 'icd',
        'laboratoire', 'imagerie', 'hospitalisation', 'bloc_operatoire',
        'maternite', 'dentaire', 'dialyse',
        'ophtalmo', 'cardiologie', 'kinesitherapie', 'oncologie', 'pma',
        'sante_mentale', 'pediatrie', 'medecine_travail', 'soins_domicile',
        'stock', 'achats', 'biomedical',
        'facturation', 'assurance', 'caisse',
        'rh', 'conges',
        'notifications',
        'prescripteurs', 'referrals', 'teleconsultation',
        'qualite', 'reclamations',
    ];

    private const ACTIONS = ['view', 'create', 'update', 'delete', 'validate', 'cancel', 'export'];

    /**
     * Clinical data (blood group, allergies, history, treatments) is kept
     * behind its own permission, separate from patients.view, so identity
     * and medical confidentiality can be governed independently — a
     * secretary or cashier can look up a patient without ever seeing why
     * they're being treated.
     *
     * laboratoire.validate_technique / laboratoire.validate_biologique
     * split the generic laboratoire.validate action in two: the workflow
     * requires two *distinct* validators (technicien then biologiste), so
     * a single "validate" permission wouldn't let one role do the first
     * step without also being able to do the second.
     *
     * imagerie.interpreter is its own permission, separate from the grid's
     * imagerie.create, because writing a report (radiologue) is a distinct
     * clinical act from performing the exam (manipulateur_radio) — the
     * grid's "create" alone can't tell them apart.
     *
     * hospitalisation.daily_note is its own permission because writing the
     * daily care record (infirmier's routine task) shouldn't require the
     * broader hospitalisation.create/update permissions used for
     * admission/discharge.
     *
     * dialyse.record_session is its own permission, separate from the
     * grid's dialyse.create/.update, because opening or adjusting a
     * dialysis *program* (frequency, dry weight, vascular access) is a
     * prescription-level decision reserved to the néphrologue, while
     * recording an individual *session* is the infirmier_dialyse's
     * routine task — a single dialyse.create wouldn't let the nurse do
     * the latter without also being able to do the former.
     *
     * pma and sante_mentale carry no extra split permission here, but are
     * deliberately excluded from `direction`'s grants below (see that
     * role's comment) — the reinforced confidentiality the cahier des
     * charges asks for is expressed as an omission, not a new permission.
     *
     * stock.dispense is its own permission, separate from the grid's
     * stock.create, because recording a dispensation (sortie de stock,
     * pharmacien) is a distinct clinical act from managing the catalogue
     * or receiving stock (gestionnaire_stock) — the grid's stock.validate
     * is reused as-is for "validation de lot" by the pharmacien.
     *
     * achats.approve is its own permission gating the purchase-order
     * approval circuit's `submit`/`approve` actions. It only decides who
     * may attempt to approve a level; *which* role is actually required
     * for a given order's current level is resolved dynamically from
     * approval_rules (see ApprovalRule / PurchaseOrderController), never
     * hardcoded here.
     *
     * biomedical.maintenance is its own permission, separate from the
     * grid's biomedical.create/.update, because logging a maintenance
     * intervention is a routine technician task distinct from managing
     * the equipment record itself.
     *
     * caisse.encaisser is its own permission, separate from the grid's
     * caisse.create, because opening/closing a cash session (accountable
     * for its own float and écart) is a distinct act from recording an
     * individual payment against it — a caissier needs both, but the
     * grid's caisse.create alone wouldn't let finance reason about the two
     * separately.
     *
     * facturation.creances is its own permission gating the read-only
     * balance-âgée (aging) report, reserved to comptable/direction — it is
     * not exposed through the grid's facturation.view so that a caissier
     * (who does have facturation.view to look up a single invoice) cannot
     * also pull the structure-wide outstanding-receivables report.
     *
     * conges.validate_all is its own permission, separate from the grid's
     * conges.validate: holding conges.validate alone only lets a role
     * (e.g. manager) validate/refuse leave requests from users who share
     * at least one site with them (their "team", via the existing
     * user_site relation — see LeaveRequestController::assertCanDecide);
     * conges.validate_all lifts that team restriction for RH/administrateur
     * /direction, who must be able to validate leave for anyone.
     *
     * appointments.override_planning is its own permission gating the
     * emergency exception to the RH-planning availability check added to
     * AppointmentController (étape 6 §3): normally an appointment cannot
     * be booked outside a practitioner's theoretical presence (schedule or
     * garde/astreinte) or during their validated leave, but a holder of
     * this permission may force it (force_override=true), traced in a
     * dedicated audit log ("derogation_planning").
     *
     * referrals.accept / referrals.refuse split the generic referrals.update
     * action in two, same rationale as laboratoire's validate_technique/
     * validate_biologique split: accepting or refusing an inter-structure
     * referral is a distinct clinical decision made by the destination
     * structure, not a generic "edit" of the referral record.
     *
     * dashboards.medical / .financier / .direction / .qualite / .export are
     * each their own permission (not the qualite/reclamations grid, which
     * governs the underlying data) because a dashboard is a read-only
     * aggregate view, not a CRUD resource — the prompt explicitly asks for
     * per-dashboard restriction (ex. "le tableau financier n'est pas
     * accessible à un infirmier"), which a single dashboards.view could not
     * express. dashboards.direction is kept separate from .medical/
     * .financier because the multi-site consolidated view is reserved to
     * direction-level roles, same rationale as facturation.creances being
     * split out from facturation.view.
     *
     * reclamations.manage_all mirrors conges.validate_all: without it, a
     * gestionnaire (ComplaintController::assertCanManage) can only act on
     * complaints assigned to them; direction/administrateur need to see and
     * act on every complaint of the structure.
     *
     * audit.view is reserved to the new `conformite` role (plus
     * administrateur via its wildcard) — it is deliberately not granted to
     * direction/directeur_medical: reading the append-only activity log is
     * a compliance function, not a management one, and keeping it off every
     * other role (including direction) is the point of a dedicated
     * conformite role rather than folding it into an existing one.
     *
     * fhir.view gates the read-only HL7 FHIR R4 endpoints (Étape 9 §1). It
     * is granted only to roles that already have broad clinical read access
     * to the underlying resources (Patient/Encounter/Condition/Observation/
     * DiagnosticReport/ServiceRequest) via the grid above, so FHIR access
     * never exceeds what the role could already see through the normal API.
     *
     * ai.consultation_summary / ai.anomaly_detection gate the two AI
     * assistance endpoints (Étape 9 §4). Both are advisory-only (never
     * auto-saved — see AiAssistanceController), so they are granted to the
     * same clinical roles that can already update a consultation.
     *
     * voice_dictation.create / .view gate the audio-upload/status
     * structure-only endpoints (no real transcription) — granted to the
     * same roles that record consultations.
     */
    private const MEDICAL_PERMISSIONS = [
        'patients_medical.view',
        'laboratoire.validate_technique',
        'laboratoire.validate_biologique',
        'imagerie.interpreter',
        'hospitalisation.daily_note',
        'dialyse.record_session',
        'stock.dispense',
        'achats.approve',
        'biomedical.maintenance',
        'caisse.encaisser',
        'facturation.creances',
        'conges.validate_all',
        'appointments.override_planning',
        'referrals.accept',
        'referrals.refuse',
        'dashboards.medical',
        'dashboards.financier',
        'dashboards.direction',
        'dashboards.qualite',
        'dashboards.export',
        'reclamations.manage_all',
        'audit.view',
        'fhir.view',
        'ai.consultation_summary',
        'ai.anomaly_detection',
        'voice_dictation.create',
        'voice_dictation.view',
    ];

    /**
     * Provisional role -> permission mapping for the socle modules only.
     * Roles tied to future clinical/logistics modules (medecin, pharmacien,
     * biologiste, ...) currently get read access to patients so the base
     * app is usable; their real permission set will grow with each module.
     */
    private const ROLE_PERMISSIONS = [
        'administrateur' => ['*'],
        'direction' => [
            'structures.view', 'structures.update', 'structures.export',
            'sites.view', 'sites.create', 'sites.update', 'sites.export',
            'users.view', 'users.create', 'users.update', 'users.export',
            'patients.view', 'patients.export',
            'appointments.view', 'appointments.export',
            'queue.view',
            'consultations.view', 'consultations.export',
            'laboratoire.view', 'laboratoire.export',
            'imagerie.view', 'imagerie.export',
            'hospitalisation.view', 'hospitalisation.create', 'hospitalisation.update', 'hospitalisation.export',
            'bloc_operatoire.view', 'bloc_operatoire.export',
            'maternite.view', 'maternite.export',
            'dentaire.view', 'dentaire.export',
            'dialyse.view', 'dialyse.export',
            // pma and sante_mentale are deliberately absent here: the
            // cahier des charges (étape 4b) asks for reinforced
            // confidentiality on these two specialties, so the
            // non-clinical `direction` role does not get view/export
            // access to them the way it does for every other specialty
            // below — only directeur_medical and the specialty's own
            // clinical role can reach that data.
            'ophtalmo.view', 'ophtalmo.export',
            'cardiologie.view', 'cardiologie.export',
            'kinesitherapie.view', 'kinesitherapie.export',
            'oncologie.view', 'oncologie.export',
            'pediatrie.view', 'pediatrie.export',
            'medecine_travail.view', 'medecine_travail.export',
            'soins_domicile.view', 'soins_domicile.export',
            'stock.view', 'stock.export',
            'achats.view', 'achats.export', 'achats.approve',
            'biomedical.view', 'biomedical.export',
            'facturation.view', 'facturation.export', 'facturation.creances',
            'assurance.view', 'assurance.export',
            'caisse.view', 'caisse.export',
            'rh.view', 'rh.export',
            'conges.view', 'conges.validate', 'conges.validate_all', 'conges.export',
            'notifications.view', 'notifications.export',
            'prescripteurs.view', 'prescripteurs.export',
            'referrals.view', 'referrals.export',
            'teleconsultation.view', 'teleconsultation.export',
            'dashboards.medical', 'dashboards.financier', 'dashboards.direction', 'dashboards.qualite', 'dashboards.export',
            'qualite.view', 'qualite.create', 'qualite.export',
            'reclamations.view', 'reclamations.create', 'reclamations.update', 'reclamations.manage_all', 'reclamations.export',
        ],
        'directeur_medical' => [
            'patients.view', 'patients.create', 'patients.update', 'patients.validate', 'patients.export',
            'patients_medical.view',
            'users.view',
            'appointments.view', 'appointments.create', 'appointments.update', 'appointments.cancel', 'appointments.export',
            'queue.view',
            'consultations.view', 'consultations.validate', 'consultations.export',
            'icd.view', 'icd.export',
            'laboratoire.view', 'laboratoire.export',
            'imagerie.view', 'imagerie.export',
            'hospitalisation.view', 'hospitalisation.export',
            'bloc_operatoire.view', 'bloc_operatoire.export',
            'maternite.view', 'maternite.export',
            'dentaire.view', 'dentaire.export',
            'dialyse.view', 'dialyse.export',
            'ophtalmo.view', 'ophtalmo.export',
            'cardiologie.view', 'cardiologie.export',
            'kinesitherapie.view', 'kinesitherapie.export',
            'oncologie.view', 'oncologie.export',
            // directeur_medical is the one non-specialist role explicitly
            // kept on pma/sante_mentale (medical oversight requires it);
            // direction, above, is not.
            'pma.view', 'pma.export',
            'sante_mentale.view', 'sante_mentale.export',
            'pediatrie.view', 'pediatrie.export',
            'medecine_travail.view', 'medecine_travail.export',
            'soins_domicile.view', 'soins_domicile.export',
            'stock.view', 'achats.view', 'achats.approve', 'biomedical.view',
            'dashboards.medical', 'dashboards.direction', 'dashboards.qualite', 'dashboards.export',
            'fhir.view', 'ai.consultation_summary', 'ai.anomaly_detection',
        ],
        'medecin' => [
            'patients.view', 'patients.create', 'patients.update', 'patients_medical.view',
            'appointments.view', 'appointments.create', 'appointments.update', 'appointments.cancel',
            'queue.view', 'queue.update',
            'consultations.view', 'consultations.create', 'consultations.update', 'consultations.validate',
            'icd.view',
            'laboratoire.view', 'laboratoire.create', 'laboratoire.cancel',
            'imagerie.view', 'imagerie.create', 'imagerie.cancel',
            'hospitalisation.view', 'hospitalisation.create', 'hospitalisation.update', 'hospitalisation.daily_note',
            'bloc_operatoire.view', 'bloc_operatoire.create',
            'referrals.view', 'referrals.create', 'referrals.accept', 'referrals.refuse', 'referrals.update',
            'teleconsultation.view', 'teleconsultation.create', 'teleconsultation.update', 'teleconsultation.cancel',
            'dashboards.medical',
            'fhir.view', 'ai.consultation_summary', 'ai.anomaly_detection',
            'voice_dictation.create', 'voice_dictation.view',
        ],
        'infirmier' => [
            'patients.view', 'patients.update', 'patients_medical.view',
            'appointments.view',
            'dashboards.medical',
            'queue.view', 'queue.create', 'queue.update',
            'consultations.view', 'consultations.create', 'consultations.update',
            'icd.view',
            'laboratoire.view',
            'imagerie.view',
            'hospitalisation.view', 'hospitalisation.daily_note',
            'fhir.view', 'ai.anomaly_detection',
            'voice_dictation.create', 'voice_dictation.view',
        ],
        'secretaire' => [
            'patients.view', 'patients.create', 'patients.update', 'structures.view', 'sites.view',
            'appointments.view', 'appointments.create', 'appointments.update', 'appointments.cancel',
            'queue.view', 'queue.create', 'queue.update',
            'prescripteurs.view', 'prescripteurs.create', 'prescripteurs.update',
            'teleconsultation.view', 'teleconsultation.create',
            'qualite.view', 'qualite.create',
            'reclamations.view', 'reclamations.create', 'reclamations.update',
        ],
        'caissier' => [
            'patients.view', 'appointments.view', 'queue.view',
            'facturation.view',
            'caisse.view', 'caisse.create', 'caisse.update', 'caisse.encaisser',
        ],
        'comptable' => [
            'structures.view', 'sites.view',
            'facturation.view', 'facturation.create', 'facturation.update', 'facturation.validate', 'facturation.cancel', 'facturation.export', 'facturation.creances',
            'assurance.view', 'assurance.create', 'assurance.update', 'assurance.export',
            'caisse.view',
            'dashboards.financier', 'dashboards.export',
        ],
        'rh' => [
            'users.view', 'users.create', 'users.update',
            'rh.view', 'rh.create', 'rh.update', 'rh.delete', 'rh.export',
            'conges.view', 'conges.validate', 'conges.validate_all', 'conges.export',
            'appointments.override_planning',
        ],
        // Validation de congés restreinte à son équipe (utilisateurs
        // partageant au moins un site avec lui, via user_site — voir
        // LeaveRequestController::assertCanDecide) : contrairement à rh,
        // pas de conges.validate_all. conges.view lui permet de voir
        // l'ensemble des demandes (utile pour coordonner les plannings),
        // mais il ne peut pas gérer les fiches personnel (pas de rh.*) —
        // pour son propre planning il reste sur l'auto-consultation
        // ouverte à tout utilisateur, comme tout le monde.
        'manager' => [
            'conges.view', 'conges.validate',
        ],
        'gestionnaire_stock' => [
            'structures.view', 'sites.view',
            'stock.view', 'stock.create', 'stock.update', 'stock.delete', 'stock.export',
            'achats.view', 'achats.approve',
        ],
        'biomedical' => [
            'sites.view',
            'biomedical.view', 'biomedical.create', 'biomedical.update', 'biomedical.maintenance', 'biomedical.export',
        ],
        'pharmacien' => [
            'patients.view', 'patients_medical.view',
            'sites.view',
            'stock.view', 'stock.dispense', 'stock.validate',
        ],
        'achats' => [
            'achats.view', 'achats.create', 'achats.update', 'achats.cancel', 'achats.export',
            'sites.view',
            'stock.view',
        ],
        'biologiste' => [
            'patients.view', 'patients_medical.view',
            'laboratoire.view', 'laboratoire.validate_biologique', 'laboratoire.export',
            'fhir.view',
        ],
        'technicien_laboratoire' => [
            'patients.view',
            'laboratoire.view', 'laboratoire.create', 'laboratoire.validate_technique',
        ],
        'radiologue' => [
            'patients.view', 'patients_medical.view',
            'imagerie.view', 'imagerie.interpreter', 'imagerie.validate', 'imagerie.export',
            'fhir.view',
        ],
        'manipulateur_radio' => [
            'patients.view',
            'imagerie.view', 'imagerie.create',
        ],
        'chirurgien' => [
            'patients.view', 'patients_medical.view',
            'bloc_operatoire.view', 'bloc_operatoire.create', 'bloc_operatoire.update',
            'bloc_operatoire.cancel', 'bloc_operatoire.validate',
        ],
        'anesthesiste' => [
            'patients.view', 'patients_medical.view',
            'bloc_operatoire.view', 'bloc_operatoire.validate',
        ],
        'sage_femme' => [
            'patients.view', 'patients_medical.view',
            'maternite.view', 'maternite.create', 'maternite.update',
        ],
        'gynecologue' => [
            'patients.view', 'patients_medical.view',
            'maternite.view', 'maternite.create', 'maternite.update', 'maternite.validate', 'maternite.export',
        ],
        'dentiste' => [
            'patients.view', 'patients_medical.view',
            'dentaire.view', 'dentaire.create', 'dentaire.update', 'dentaire.export',
        ],
        'nephrologue' => [
            'patients.view', 'patients_medical.view',
            'dialyse.view', 'dialyse.create', 'dialyse.update', 'dialyse.export',
        ],
        'infirmier_dialyse' => [
            'patients.view',
            'dialyse.view', 'dialyse.record_session',
        ],
        'ophtalmologue' => [
            'patients.view', 'patients_medical.view',
            'ophtalmo.view', 'ophtalmo.create', 'ophtalmo.update', 'ophtalmo.export',
        ],
        'cardiologue' => [
            'patients.view', 'patients_medical.view',
            'cardiologie.view', 'cardiologie.create', 'cardiologie.update', 'cardiologie.export',
        ],
        'kinesitherapeute' => [
            'patients.view', 'patients_medical.view',
            'kinesitherapie.view', 'kinesitherapie.create', 'kinesitherapie.update', 'kinesitherapie.export',
        ],
        'oncologue' => [
            'patients.view', 'patients_medical.view',
            'oncologie.view', 'oncologie.create', 'oncologie.update', 'oncologie.export',
        ],
        // Highly sensitive: unlike every other specialty role, this list
        // is not mirrored by a `direction.pma.*` grant above — only
        // specialiste_pma and directeur_medical can reach this data.
        'specialiste_pma' => [
            'patients.view', 'patients_medical.view',
            'pma.view', 'pma.create', 'pma.update', 'pma.export',
        ],
        // Same reinforced-confidentiality treatment as specialiste_pma:
        // psychiatre and psychologue are the only clinical roles (besides
        // directeur_medical) with any sante_mentale.* permission.
        'psychiatre' => [
            'patients.view', 'patients_medical.view',
            'sante_mentale.view', 'sante_mentale.create', 'sante_mentale.update', 'sante_mentale.export',
        ],
        'psychologue' => [
            'patients.view', 'patients_medical.view',
            'sante_mentale.view', 'sante_mentale.create', 'sante_mentale.update', 'sante_mentale.export',
        ],
        'pediatre' => [
            'patients.view', 'patients_medical.view',
            'pediatrie.view', 'pediatrie.create', 'pediatrie.update', 'pediatrie.export',
        ],
        'medecin_travail' => [
            'patients.view', 'patients_medical.view',
            'medecine_travail.view', 'medecine_travail.create', 'medecine_travail.update', 'medecine_travail.export',
        ],
        'infirmier_domicile' => [
            'patients.view', 'patients_medical.view',
            'soins_domicile.view', 'soins_domicile.create', 'soins_domicile.update',
        ],
        // Étape 9 §3 : rôle dédié à la lecture du journal d'audit
        // (append-only, jamais modifiable — voir AuditLogController et
        // app/Domain/Audit/README.md). Volontairement minimal : ce rôle ne
        // porte que audit.view, aucun autre accès clinique ou de gestion.
        'conformite' => [
            'audit.view',
        ],
    ];

    public function run(): void
    {
        $guard = 'sanctum';

        $permissions = collect(self::MODULES)
            ->crossJoin(self::ACTIONS)
            ->map(fn ($pair) => "{$pair[0]}.{$pair[1]}")
            ->concat(self::MEDICAL_PERMISSIONS);

        $permissions->each(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => $guard]));

        foreach (self::ROLE_PERMISSIONS as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);

            $role->syncPermissions(
                $rolePermissions === ['*'] ? Permission::where('guard_name', $guard)->get() : $rolePermissions
            );
        }
    }
}
