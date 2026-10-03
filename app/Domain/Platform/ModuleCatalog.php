<?php

namespace App\Domain\Platform;

use App\Domain\Structure\Models\StructureModule;

/**
 * Catalogue commercial des modules (grille du modèle économique), distinct
 * de RolePermissionSeeder::MODULES qui reste la taxonomie des permissions :
 * un module commercial peut couvrir plusieurs familles de permissions
 * (finance_avancee = assurance + créances, rh = rh + conges) ou aucune
 * (multi_sites, qui ne fait que limiter le nombre de sites actifs).
 *
 * Règle d'activation unique, lue par le middleware `module:<clé>`, par
 * /auth/me et par l'écran plateforme — jamais recalculée ailleurs :
 *  - un module socle est toujours actif, quelle que soit la base ;
 *  - un module premium est actif sauf s'il existe une ligne
 *    structure_modules explicitement désactivée (pas de ligne = actif),
 *    ce qui laisse intactes les structures existantes.
 *
 * Tableaux de bord, audit, IA, FHIR et dictée vocale ne sont volontairement
 * pas des modules : toujours disponibles (l'audit ne doit jamais pouvoir
 * être coupé), ou pas encore commercialisés.
 */
final class ModuleCatalog
{
    public const CORE = [
        'structures' => 'Structures',
        'sites' => 'Sites (un site actif)',
        'users' => 'Comptes utilisateurs',
        'patients' => 'Patients',
        'appointments' => 'Rendez-vous',
        'queue' => "File d'attente",
        'consultations' => 'Consultations',
        'icd' => 'Codification CIM',
        'facturation' => 'Facturation',
        'caisse' => 'Caisse',
        'notifications' => 'Notifications',
    ];

    public const PREMIUM = [
        'laboratoire' => 'Laboratoire',
        'imagerie' => 'Imagerie',
        'hospitalisation' => 'Hospitalisation',
        'bloc_operatoire' => 'Bloc opératoire',
        'maternite' => 'Maternité',
        'dentaire' => 'Dentaire',
        'dialyse' => 'Dialyse',
        'ophtalmo' => 'Ophtalmologie',
        'cardiologie' => 'Cardiologie',
        'kinesitherapie' => 'Kinésithérapie',
        'oncologie' => 'Oncologie',
        'pma' => 'PMA',
        'sante_mentale' => 'Santé mentale',
        'pediatrie' => 'Pédiatrie',
        'medecine_travail' => 'Médecine du travail',
        'soins_domicile' => 'Soins à domicile',
        'stock' => 'Pharmacie et stocks',
        'achats' => 'Achats',
        'biomedical' => 'Équipements biomédicaux',
        'rh' => 'RH (personnel, plannings, gardes, congés)',
        'finance_avancee' => 'Finance avancée (assurances, tiers-payant, créances)',
        'multi_sites' => 'Multi-sites (2ᵉ site actif et au-delà)',
        'prescripteurs' => 'Prescripteurs externes et portail prescripteur',
        'referrals' => 'Référencement inter-structures',
        'teleconsultation' => 'Téléconsultation',
        'qualite' => 'Qualité (enquêtes de satisfaction)',
        'reclamations' => 'Réclamations',
    ];

    public static function isCore(string $module): bool
    {
        return array_key_exists($module, self::CORE);
    }

    public static function isPremium(string $module): bool
    {
        return array_key_exists($module, self::PREMIUM);
    }

    public static function label(string $module): string
    {
        return self::CORE[$module] ?? self::PREMIUM[$module] ?? $module;
    }

    /** @return list<string> premium modules explicitly switched off for this structure */
    public static function disabledFor(?int $structureId): array
    {
        if (! $structureId) {
            return [];
        }

        return StructureModule::query()
            ->where('structure_id', $structureId)
            ->where('is_active', false)
            ->whereIn('module', array_keys(self::PREMIUM))
            ->pluck('module')
            ->all();
    }

    /** @return list<string> */
    public static function activeFor(?int $structureId): array
    {
        $disabled = self::disabledFor($structureId);

        return array_values(array_merge(
            array_keys(self::CORE),
            array_diff(array_keys(self::PREMIUM), $disabled),
        ));
    }

    public static function isActiveFor(?int $structureId, string $module): bool
    {
        if (self::isCore($module)) {
            return true;
        }

        return ! in_array($module, self::disabledFor($structureId), true);
    }

    public static function inactiveMessage(string $module): string
    {
        return 'Le module « '.self::label($module)." » n'est pas activé pour votre structure. Contactez Saliha Health pour l'ajouter à votre abonnement.";
    }
}
