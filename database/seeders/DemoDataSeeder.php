<?php

namespace Database\Seeders;

use App\Domain\Achats\Models\ApprovalRule;
use App\Domain\Achats\Models\Supplier;
use App\Domain\Assurance\Models\InsuranceConvention;
use App\Domain\Assurance\Models\InsuranceConventionCoverageRule;
use App\Domain\Assurance\Models\InsuranceProvider;
use App\Domain\Assurance\Models\PatientInsuranceCoverage;
use App\Domain\Biomedical\Models\BiomedicalEquipment;
use App\Domain\Biomedical\Models\EquipmentMaintenance;
use App\Domain\Caisse\Models\CashSession;
use App\Domain\Caisse\Models\Payment;
use App\Domain\Consultation\Models\Consultation;
use App\Domain\Facturation\Models\BillableItem;
use App\Domain\Facturation\Models\Invoice;
use App\Domain\Facturation\Models\Quote;
use App\Domain\Facturation\Models\ServiceTariff;
use App\Domain\Hospitalisation\Models\Bed;
use App\Domain\Hospitalisation\Models\Ward;
use App\Domain\Patient\Models\Patient;
use App\Domain\Patient\Models\PatientMedicalInfo;
use App\Domain\Pharmacie\Models\Product;
use App\Domain\Pharmacie\Models\ProductBatch;
use App\Domain\Pharmacie\Models\StockThreshold;
use App\Domain\Qualite\Models\Complaint;
use App\Domain\Qualite\Models\PatientSatisfactionSurvey;
use App\Domain\Rh\Models\EmployeeProfile;
use App\Domain\Rh\Models\LeaveRequest;
use App\Domain\Rh\Models\WorkSchedule;
use App\Domain\Shared\Billing\BillingService;
use App\Domain\Shared\Billing\InsuranceCoverageService;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $structure = Structure::query()->firstOrCreate(
            ['code' => 'DEMO-001'],
            [
                'legal_name' => 'Polyclinique Sainte-Marie SARL',
                'trade_name' => 'Polyclinique Sainte-Marie',
                'type' => 'polyclinique',
                'address' => 'Boulevard de la République',
                'city' => 'Abidjan',
                'country' => "Côte d'Ivoire",
                'phone' => '+225 27 22 00 00 00',
                'email' => 'contact@sainte-marie.demo',
                'registration_number' => 'RC-ABJ-2024-B-1234',
                'currency' => 'XOF',
                'locale' => 'fr',
                'is_active' => true,
            ]
        );

        $sites = collect(['Site Plateau', 'Site Cocody', 'Site Marcory'])
            ->map(fn (string $name) => Site::query()->firstOrCreate(
                ['structure_id' => $structure->id, 'name' => $name],
                ['address' => "Adresse $name", 'city' => 'Abidjan', 'is_active' => true]
            ));

        $demoUsers = [
            ['role' => 'administrateur', 'first_name' => 'Awa', 'last_name' => 'Koné', 'email' => 'admin@sainte-marie.demo'],
            ['role' => 'direction', 'first_name' => 'Jean', 'last_name' => 'Kouassi', 'email' => 'direction@sainte-marie.demo'],
            ['role' => 'directeur_medical', 'first_name' => 'Fatou', 'last_name' => 'Diabaté', 'email' => 'dirmed@sainte-marie.demo'],
            ['role' => 'medecin', 'first_name' => 'Yves', 'last_name' => 'Brou', 'email' => 'medecin@sainte-marie.demo'],
            ['role' => 'infirmier', 'first_name' => 'Aïcha', 'last_name' => 'Traoré', 'email' => 'infirmier@sainte-marie.demo'],
            ['role' => 'secretaire', 'first_name' => 'Marie', 'last_name' => 'Yao', 'email' => 'secretaire@sainte-marie.demo'],
            ['role' => 'caissier', 'first_name' => 'Paul', 'last_name' => 'N\'Guessan', 'email' => 'caissier@sainte-marie.demo'],
            ['role' => 'comptable', 'first_name' => 'Hortense', 'last_name' => 'Assouan', 'email' => 'comptable@sainte-marie.demo'],
            ['role' => 'technicien_laboratoire', 'first_name' => 'Serge', 'last_name' => 'Adou', 'email' => 'technicien.labo@sainte-marie.demo'],
            ['role' => 'biologiste', 'first_name' => 'Nadège', 'last_name' => 'Bamba', 'email' => 'biologiste@sainte-marie.demo'],
            ['role' => 'manipulateur_radio', 'first_name' => 'Ibrahim', 'last_name' => 'Coulibaly', 'email' => 'manip.radio@sainte-marie.demo'],
            ['role' => 'radiologue', 'first_name' => 'Aminata', 'last_name' => 'Koné', 'email' => 'radiologue@sainte-marie.demo'],
            ['role' => 'chirurgien', 'first_name' => 'Moussa', 'last_name' => 'Ouattara', 'email' => 'chirurgien@sainte-marie.demo'],
            ['role' => 'anesthesiste', 'first_name' => 'Salimata', 'last_name' => 'Sanogo', 'email' => 'anesthesiste@sainte-marie.demo'],
            ['role' => 'gestionnaire_stock', 'first_name' => 'Kader', 'last_name' => 'Sylla', 'email' => 'gestionnaire.stock@sainte-marie.demo'],
            ['role' => 'pharmacien', 'first_name' => 'Josiane', 'last_name' => 'Aka', 'email' => 'pharmacien@sainte-marie.demo'],
            ['role' => 'achats', 'first_name' => 'Bakary', 'last_name' => 'Diarra', 'email' => 'achats@sainte-marie.demo'],
            ['role' => 'biomedical', 'first_name' => 'Estelle', 'last_name' => 'Kouamé', 'email' => 'biomedical@sainte-marie.demo'],
        ];

        foreach ($demoUsers as $data) {
            $user = User::query()->firstOrCreate(
                ['email' => $data['email']],
                [
                    'structure_id' => $structure->id,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'phone' => '+225 07 00 00 00 00',
                    'password' => 'password',
                    'is_active' => true,
                ]
            );

            if (! $user->hasRole($data['role'])) {
                $user->assignRole($data['role']);
            }

            if ($user->sites()->count() === 0) {
                $user->sites()->sync($sites->random(min(2, $sites->count()))->pluck('id'));
            }
        }

        // --- RH : utilisateurs et données de démonstration (étape 13) ---
        // Bloc distinct de la boucle $demoUsers ci-dessus : cette dernière
        // affecte les sites de façon aléatoire (->random(...)), alors que
        // le scénario RH (manager validant les congés de son équipe, garde
        // vs astreinte filtrables par site) a besoin d'affectations de
        // site déterministes et non chevauchantes.
        if (User::query()->where('email', 'rh@sainte-marie.demo')->doesntExist()) {
            $rhUser = User::query()->firstOrCreate(
                ['email' => 'rh@sainte-marie.demo'],
                [
                    'structure_id' => $structure->id,
                    'first_name' => 'Solange',
                    'last_name' => 'Ehouman',
                    'phone' => '+225 07 00 00 01 01',
                    'password' => 'password',
                    'is_active' => true,
                ]
            );
            $rhUser->assignRole('rh');
            $rhUser->sites()->sync($sites->pluck('id'));

            $manager1 = User::query()->firstOrCreate(
                ['email' => 'manager1@sainte-marie.demo'],
                [
                    'structure_id' => $structure->id,
                    'first_name' => 'Konan',
                    'last_name' => 'Kouadio',
                    'phone' => '+225 07 00 00 01 02',
                    'password' => 'password',
                    'is_active' => true,
                ]
            );
            $manager1->assignRole('manager');
            $manager1->sites()->sync([$sites->get(0)->id]);

            $manager2 = User::query()->firstOrCreate(
                ['email' => 'manager2@sainte-marie.demo'],
                [
                    'structure_id' => $structure->id,
                    'first_name' => 'Affoué',
                    'last_name' => 'Angoran',
                    'phone' => '+225 07 00 00 01 03',
                    'password' => 'password',
                    'is_active' => true,
                ]
            );
            $manager2->assignRole('manager');
            $manager2->sites()->sync([$sites->get(2)->id]);

            $personnelPlateau = User::query()->firstOrCreate(
                ['email' => 'personnel.plateau@sainte-marie.demo'],
                [
                    'structure_id' => $structure->id,
                    'first_name' => 'Chantal',
                    'last_name' => 'Zadi',
                    'phone' => '+225 07 00 00 01 04',
                    'password' => 'password',
                    'is_active' => true,
                ]
            );
            $personnelPlateau->assignRole('infirmier');
            $personnelPlateau->sites()->sync([$sites->get(0)->id]);
        } else {
            $rhUser = User::query()->where('email', 'rh@sainte-marie.demo')->first();
            $manager1 = User::query()->where('email', 'manager1@sainte-marie.demo')->first();
            $manager2 = User::query()->where('email', 'manager2@sainte-marie.demo')->first();
            $personnelPlateau = User::query()->where('email', 'personnel.plateau@sainte-marie.demo')->first();
        }

        $medecinDemoUser = User::query()->where('email', 'medecin@sainte-marie.demo')->first();
        $todayWeekday = now()->dayOfWeek;

        if (EmployeeProfile::query()->where('structure_id', $structure->id)->doesntExist()) {
            EmployeeProfile::create([
                'structure_id' => $structure->id,
                'user_id' => $personnelPlateau->id,
                'date_embauche' => now()->subYears(3)->toDateString(),
                'type_contrat' => 'CDI',
                'statut_emploi' => 'actif',
                'qualification' => "Infirmière diplômée d'État",
                'numero_ordre' => 'ONI-CI-2023-0456',
            ]);

            if ($medecinDemoUser) {
                EmployeeProfile::create([
                    'structure_id' => $structure->id,
                    'user_id' => $medecinDemoUser->id,
                    'date_embauche' => now()->subYears(6)->toDateString(),
                    'type_contrat' => 'CDI',
                    'statut_emploi' => 'en_conge',
                    'qualification' => 'Médecin généraliste',
                    'numero_ordre' => 'ONM-CI-2019-1123',
                ]);
            }

            EmployeeProfile::create([
                'structure_id' => $structure->id,
                'user_id' => $manager1->id,
                'date_embauche' => now()->subYears(4)->toDateString(),
                'type_contrat' => 'CDI',
                'statut_emploi' => 'actif',
                'qualification' => 'Responsable de site',
                'numero_ordre' => null,
            ]);
        }

        if (WorkSchedule::query()->where('structure_id', $structure->id)->doesntExist()) {
            // Horaire récurrent : ce jour de la semaine, chaque semaine —
            // donc toujours actif "aujourd'hui" quel que soit le jour
            // d'exécution du seeder.
            WorkSchedule::create([
                'structure_id' => $structure->id,
                'user_id' => $personnelPlateau->id,
                'site_id' => $sites->get(0)->id,
                'jour_semaine' => $todayWeekday,
                'date' => null,
                'heure_debut' => '08:00:00',
                'heure_fin' => '16:00:00',
                'type' => 'normal',
            ]);

            // Exception ponctuelle (ex. jour férié travaillé).
            WorkSchedule::create([
                'structure_id' => $structure->id,
                'user_id' => $personnelPlateau->id,
                'site_id' => $sites->get(0)->id,
                'jour_semaine' => null,
                'date' => now()->addDays(3)->toDateString(),
                'heure_debut' => '08:00:00',
                'heure_fin' => '16:00:00',
                'type' => 'normal',
            ]);

            if ($medecinDemoUser) {
                // Garde 24h, récurrente ce jour-ci : l'endpoint "qui est de
                // garde maintenant" reste démontrable quel que soit le jour.
                WorkSchedule::create([
                    'structure_id' => $structure->id,
                    'user_id' => $medecinDemoUser->id,
                    'site_id' => $sites->get(0)->id,
                    'jour_semaine' => $todayWeekday,
                    'date' => null,
                    'heure_debut' => '00:00:00',
                    'heure_fin' => '23:59:59',
                    'type' => 'garde',
                ]);
            }

            // Astreinte 24h sur un site différent de la garde ci-dessus (et
            // un utilisateur différent), pour un filtre par site démonstratif.
            WorkSchedule::create([
                'structure_id' => $structure->id,
                'user_id' => $manager2->id,
                'site_id' => $sites->get(2)->id,
                'jour_semaine' => $todayWeekday,
                'date' => null,
                'heure_debut' => '00:00:00',
                'heure_fin' => '23:59:59',
                'type' => 'astreinte',
            ]);
        }

        if (LeaveRequest::query()->where('structure_id', $structure->id)->doesntExist()) {
            $infirmierDemoUser = User::query()->where('email', 'infirmier@sainte-marie.demo')->first();
            $secretaireDemoUser = User::query()->where('email', 'secretaire@sainte-marie.demo')->first();

            // En attente, chevauche délibérément l'horaire récurrent
            // ci-dessus (même jour de semaine, 7 jours plus tard) pour
            // démontrer l'avertissement de chevauchement lors de la
            // validation par le manager.
            LeaveRequest::create([
                'structure_id' => $structure->id,
                'user_id' => $personnelPlateau->id,
                'type' => 'conge_annuel',
                'date_debut' => now()->addDays(7)->toDateString(),
                'date_fin' => now()->addDays(9)->toDateString(),
                'statut' => 'demande',
                'validated_by' => null,
                'commentaire' => null,
            ]);

            if ($infirmierDemoUser) {
                LeaveRequest::create([
                    'structure_id' => $structure->id,
                    'user_id' => $infirmierDemoUser->id,
                    'type' => 'conge_annuel',
                    'date_debut' => now()->subDays(30)->toDateString(),
                    'date_fin' => now()->subDays(23)->toDateString(),
                    'statut' => 'valide',
                    'validated_by' => $rhUser->id,
                    'commentaire' => 'Congés annuels validés.',
                ]);
            }

            if ($secretaireDemoUser) {
                LeaveRequest::create([
                    'structure_id' => $structure->id,
                    'user_id' => $secretaireDemoUser->id,
                    'type' => 'maladie',
                    'date_debut' => now()->subDays(15)->toDateString(),
                    'date_fin' => now()->subDays(14)->toDateString(),
                    'statut' => 'refuse',
                    'validated_by' => $rhUser->id,
                    'commentaire' => 'Justificatif médical manquant.',
                ]);
            }
        }

        if (Patient::query()->where('structure_id', $structure->id)->doesntExist()) {
            Patient::factory()
                ->count(8)
                ->for($structure)
                ->has(PatientMedicalInfo::factory(), 'medicalInfo')
                ->create();
        }

        if (Ward::query()->where('structure_id', $structure->id)->doesntExist()) {
            $wardNames = ['Médecine générale', 'Chirurgie'];
            $bedStatuses = ['libre', 'libre', 'libre', 'occupe', 'entretien'];

            foreach ($sites as $site) {
                foreach ($wardNames as $wardName) {
                    $ward = Ward::create([
                        'structure_id' => $structure->id,
                        'site_id' => $site->id,
                        'name' => "$wardName — {$site->name}",
                    ]);

                    foreach ($bedStatuses as $index => $status) {
                        Bed::create([
                            'structure_id' => $structure->id,
                            'site_id' => $site->id,
                            'ward_id' => $ward->id,
                            'room_number' => (string) (100 + $index),
                            'bed_label' => 'Lit '.($index + 1),
                            'status' => $status,
                        ]);
                    }
                }
            }
        }

        $biomedicalUser = User::query()->where('email', 'biomedical@sainte-marie.demo')->first();
        $primarySite = $sites->first();
        $secondarySite = $sites->get(1) ?? $primarySite;

        if (Supplier::query()->where('structure_id', $structure->id)->doesntExist()) {
            $suppliers = collect([
                ['nom' => 'PharmaDistrib CI', 'contact' => 'contact@pharmadistrib.ci', 'conditions_commerciales' => 'Paiement à 30 jours'],
                ['nom' => 'MedEquip Afrique', 'contact' => 'ventes@medequip-afrique.com', 'conditions_commerciales' => 'Paiement à réception'],
                ['nom' => 'BioSupply Ouest', 'contact' => 'commandes@biosupply-ouest.com', 'conditions_commerciales' => 'Paiement à 45 jours'],
            ])->map(fn (array $data) => Supplier::create(['structure_id' => $structure->id, ...$data]));
        } else {
            $suppliers = Supplier::query()->where('structure_id', $structure->id)->get();
        }

        if (Product::query()->where('structure_id', $structure->id)->doesntExist()) {
            $products = collect([
                ['nom_commercial' => 'Paracétamol 500mg', 'dci' => 'Paracétamol', 'forme_galenique' => 'comprimé', 'dosage' => '500mg', 'categorie' => 'medicament', 'unite_vente' => 'boite'],
                ['nom_commercial' => 'Amoxicilline 250mg', 'dci' => 'Amoxicilline', 'forme_galenique' => 'gélule', 'dosage' => '250mg', 'categorie' => 'medicament', 'unite_vente' => 'boite'],
                ['nom_commercial' => 'Sérum physiologique 0,9%', 'dci' => 'Chlorure de sodium', 'forme_galenique' => 'solution', 'dosage' => '500ml', 'categorie' => 'medicament', 'unite_vente' => 'flacon'],
                ['nom_commercial' => 'Compresses stériles', 'dci' => 'N/A', 'forme_galenique' => 'compresse', 'dosage' => 'N/A', 'categorie' => 'consommable', 'unite_vente' => 'boite'],
                ['nom_commercial' => 'Gants latex non stériles', 'dci' => 'N/A', 'forme_galenique' => 'gant', 'dosage' => 'N/A', 'categorie' => 'consommable', 'unite_vente' => 'boite'],
                ['nom_commercial' => 'Seringues 5ml', 'dci' => 'N/A', 'forme_galenique' => 'seringue', 'dosage' => '5ml', 'categorie' => 'dispositif_medical', 'unite_vente' => 'unite'],
            ])->map(fn (array $data) => Product::create(['structure_id' => $structure->id, 'actif' => true, ...$data]));

            $batchPlans = [
                // [product index, site, numero_lot suffix, days from now to expiry, quantite_stock, prix_achat]
                [0, $primarySite, 'A1', 400, 120, 850],
                [0, $primarySite, 'A2', -10, 0, 850],
                [1, $primarySite, 'B1', 300, 60, 1200],
                [1, $secondarySite, 'B2', 15, 40, 1200],
                [2, $primarySite, 'C1', -5, 30, 600],
                [3, $primarySite, 'D1', 500, 200, 2500],
                [4, $secondarySite, 'E1', 500, 15, 3000],
                [5, $primarySite, 'F1', 700, 500, 45],
            ];

            foreach ($batchPlans as $index => [$productIdx, $site, $suffix, $days, $qty, $prix]) {
                ProductBatch::create([
                    'structure_id' => $structure->id,
                    'product_id' => $products[$productIdx]->id,
                    'site_id' => $site->id,
                    'supplier_id' => $suppliers[$index % max($suppliers->count(), 1)]->id ?? null,
                    'numero_lot' => "LOT-{$suffix}-".now()->year,
                    'date_peremption' => now()->addDays($days)->toDateString(),
                    'quantite_stock' => $qty,
                    'prix_achat_unitaire' => $prix,
                ]);
            }

            // Seuils bas volontairement déclenchés : gants (produit 4) et sérum (produit 2)
            StockThreshold::create(['structure_id' => $structure->id, 'product_id' => $products[4]->id, 'site_id' => $secondarySite->id, 'seuil_minimum' => 50]);
            StockThreshold::create(['structure_id' => $structure->id, 'product_id' => $products[2]->id, 'site_id' => $primarySite->id, 'seuil_minimum' => 100]);
            StockThreshold::create(['structure_id' => $structure->id, 'product_id' => $products[0]->id, 'site_id' => $primarySite->id, 'seuil_minimum' => 20]);
        }

        if (ApprovalRule::query()->where('structure_id', $structure->id)->doesntExist()) {
            ApprovalRule::create(['structure_id' => $structure->id, 'level' => 1, 'min_amount' => 0, 'role_name' => 'gestionnaire_stock']);
            ApprovalRule::create(['structure_id' => $structure->id, 'level' => 2, 'min_amount' => 500000, 'role_name' => 'direction']);
        }

        if (BiomedicalEquipment::query()->where('structure_id', $structure->id)->doesntExist()) {
            $equipmentPlans = [
                ['nom' => 'Échographe portable', 'categorie' => 'imagerie', 'numero_serie' => 'ECHO-2023-011', 'site' => $primarySite, 'statut' => 'en_service'],
                ['nom' => 'Bistouri électrique', 'categorie' => 'bloc', 'numero_serie' => 'BIST-2022-004', 'site' => $primarySite, 'statut' => 'en_service'],
                ['nom' => 'Analyseur biochimie', 'categorie' => 'laboratoire', 'numero_serie' => 'ANLZ-2021-019', 'site' => $secondarySite, 'statut' => 'en_maintenance'],
                ['nom' => 'Respirateur de réanimation', 'categorie' => 'reanimation', 'numero_serie' => 'RESP-2020-007', 'site' => $primarySite, 'statut' => 'en_service'],
            ];

            $equipments = collect($equipmentPlans)->map(fn (array $data) => BiomedicalEquipment::create([
                'structure_id' => $structure->id,
                'site_id' => $data['site']->id,
                'supplier_id' => $suppliers->first()?->id,
                'nom' => $data['nom'],
                'categorie' => $data['categorie'],
                'numero_serie' => $data['numero_serie'],
                'date_acquisition' => now()->subYears(2)->toDateString(),
                'date_fin_garantie' => now()->addMonths(6)->toDateString(),
                'statut' => $data['statut'],
            ]));

            // Maintenance à venir (planifiée, future)
            EquipmentMaintenance::create([
                'structure_id' => $structure->id,
                'biomedical_equipment_id' => $equipments[0]->id,
                'intervenant_user_id' => $biomedicalUser?->id,
                'type' => 'preventive',
                'date_prevue' => now()->addDays(10)->toDateString(),
                'date_realisee' => null,
                'intervenant_externe' => null,
                'cout' => null,
                'description' => 'Contrôle préventif annuel',
                'statut' => 'planifiee',
            ]);

            // Maintenance en retard (planifiée, date dépassée)
            EquipmentMaintenance::create([
                'structure_id' => $structure->id,
                'biomedical_equipment_id' => $equipments[2]->id,
                'intervenant_user_id' => $biomedicalUser?->id,
                'type' => 'corrective',
                'date_prevue' => now()->subDays(5)->toDateString(),
                'date_realisee' => null,
                'intervenant_externe' => 'TechService Abidjan',
                'cout' => null,
                'description' => 'Panne signalée sur le module de dosage',
                'statut' => 'planifiee',
            ]);

            // Maintenance déjà réalisée (historique)
            EquipmentMaintenance::create([
                'structure_id' => $structure->id,
                'biomedical_equipment_id' => $equipments[1]->id,
                'intervenant_user_id' => $biomedicalUser?->id,
                'type' => 'preventive',
                'date_prevue' => now()->subMonths(1)->toDateString(),
                'date_realisee' => now()->subMonths(1)->addDay()->toDateString(),
                'intervenant_externe' => null,
                'cout' => 35000,
                'description' => 'Vérification et étalonnage',
                'statut' => 'realisee',
            ]);
        }

        // --- Facturation, Caisse, Assurances/IPM (étape 12) ---

        if (ServiceTariff::query()->where('structure_id', $structure->id)->doesntExist()) {
            // Les codes doivent correspondre exactement à Billable::billingTariffCode()
            // (Consultation, LabOrder, ImagingOrder) : BillingService::recordService()
            // ne résout un tarif que par égalité stricte de code, jamais par catégorie.
            ServiceTariff::create(['structure_id' => $structure->id, 'code' => 'CONSULTATION_GENERALE', 'libelle' => 'Consultation générale', 'categorie' => 'consultation', 'prix_unitaire' => 15000, 'actif' => true]);
            ServiceTariff::create(['structure_id' => $structure->id, 'code' => 'LABORATOIRE_ANALYSE', 'libelle' => 'Analyse de laboratoire', 'categorie' => 'laboratoire', 'prix_unitaire' => 25000, 'actif' => true]);
            ServiceTariff::create(['structure_id' => $structure->id, 'code' => 'IMAGERIE_RADIO', 'libelle' => 'Radiographie standard', 'categorie' => 'imagerie', 'prix_unitaire' => 40000, 'actif' => true]);
        }

        if (InsuranceProvider::query()->where('structure_id', $structure->id)->doesntExist()) {
            $providerPrivee = InsuranceProvider::create(['structure_id' => $structure->id, 'nom' => 'NSIA Assurances', 'type' => 'assurance_privee', 'contact' => 'gestion@nsia-assurances.ci']);
            $providerIpm = InsuranceProvider::create(['structure_id' => $structure->id, 'nom' => "IPM Côte d'Ivoire", 'type' => 'ipm', 'contact' => 'contact@ipm-ci.org']);

            $conventionPrivee = InsuranceConvention::create(['structure_id' => $structure->id, 'insurance_provider_id' => $providerPrivee->id, 'nom' => 'Convention Entreprise Confort', 'date_debut' => now()->subYear()->toDateString(), 'actif' => true]);
            $conventionIpm = InsuranceConvention::create(['structure_id' => $structure->id, 'insurance_provider_id' => $providerIpm->id, 'nom' => 'Convention IPM Salariés', 'date_debut' => now()->subYear()->toDateString(), 'actif' => true]);

            // Scénario 1 : taux simple, sans plafond
            InsuranceConventionCoverageRule::create(['insurance_convention_id' => $conventionPrivee->id, 'categorie' => 'consultation', 'taux_couverture' => 70, 'plafond_montant' => null, 'exclu' => false]);
            // Scénario 2 : taux avec plafond
            InsuranceConventionCoverageRule::create(['insurance_convention_id' => $conventionPrivee->id, 'categorie' => 'laboratoire', 'taux_couverture' => 80, 'plafond_montant' => 15000, 'exclu' => false]);
            // Scénario 3 : catégorie exclue
            InsuranceConventionCoverageRule::create(['insurance_convention_id' => $conventionPrivee->id, 'categorie' => 'imagerie', 'taux_couverture' => 0, 'plafond_montant' => null, 'exclu' => true]);

            InsuranceConventionCoverageRule::create(['insurance_convention_id' => $conventionIpm->id, 'categorie' => 'consultation', 'taux_couverture' => 100, 'plafond_montant' => null, 'exclu' => false]);
            InsuranceConventionCoverageRule::create(['insurance_convention_id' => $conventionIpm->id, 'categorie' => 'laboratoire', 'taux_couverture' => 60, 'plafond_montant' => 20000, 'exclu' => false]);
        }

        $conventionPrivee = InsuranceConvention::query()->where('structure_id', $structure->id)->where('nom', 'Convention Entreprise Confort')->first();
        $conventionIpm = InsuranceConvention::query()->where('structure_id', $structure->id)->where('nom', 'Convention IPM Salariés')->first();

        $patients = Patient::query()->where('structure_id', $structure->id)->orderBy('id')->get();

        // --- Qualité : évaluations de satisfaction et réclamations (étape 14) ---

        if (PatientSatisfactionSurvey::query()->where('structure_id', $structure->id)->doesntExist()
            && $patients->count() >= 5) {
            // Étalées sur les 60 derniers jours pour peupler plusieurs
            // buckets du futur graphique de tendance (jour/semaine/mois/
            // trimestre — voir QualityDashboardService).
            $surveyPlans = [
                ['patient' => 0, 'service' => 'Consultation', 'note' => 9, 'days' => 3, 'commentaire' => 'Accueil très professionnel et prise en charge rapide.'],
                ['patient' => 1, 'service' => 'Laboratoire', 'note' => 8, 'days' => 7, 'commentaire' => null],
                ['patient' => 2, 'service' => 'Imagerie', 'note' => 10, 'days' => 12, 'commentaire' => 'Personnel attentif, résultats communiqués rapidement.'],
                ['patient' => 3, 'service' => 'Hospitalisation', 'note' => 7, 'days' => 18, 'commentaire' => null],
                ['patient' => 4, 'service' => 'Consultation', 'note' => 6, 'days' => 24, 'commentaire' => "Temps d'attente un peu long mais personnel attentif."],
                ['patient' => 0, 'service' => 'Laboratoire', 'note' => 9, 'days' => 30, 'commentaire' => null],
                ['patient' => 1, 'service' => 'Imagerie', 'note' => 8, 'days' => 36, 'commentaire' => "Cabine d'examen propre, explications claires avant l'examen."],
                ['patient' => 2, 'service' => 'Hospitalisation', 'note' => 10, 'days' => 42, 'commentaire' => null],
                ['patient' => 3, 'service' => 'Consultation', 'note' => 5, 'days' => 50, 'commentaire' => "Salle d'attente bondée, manque de signalétique."],
                ['patient' => 4, 'service' => 'Laboratoire', 'note' => 9, 'days' => 58, 'commentaire' => null],
            ];

            foreach ($surveyPlans as $plan) {
                PatientSatisfactionSurvey::create([
                    'structure_id' => $structure->id,
                    'patient_id' => $patients[$plan['patient']]->id,
                    'prestation_type' => null,
                    'prestation_id' => null,
                    'service' => $plan['service'],
                    'note' => $plan['note'],
                    'commentaire' => $plan['commentaire'],
                    'date' => now()->subDays($plan['days'])->toDateString(),
                ]);
            }
        }

        $secretaireUser = User::query()->where('email', 'secretaire@sainte-marie.demo')->first();
        $directionUser = User::query()->where('email', 'direction@sainte-marie.demo')->first();

        if ($secretaireUser && $directionUser && $patients->count() >= 4
            && Complaint::query()->where('structure_id', $structure->id)->doesntExist()) {
            // Quatre réclamations couvrant chaque état du workflow strict
            // ouverte → en_cours → resolue → close (abort_if() dans
            // ComplaintController), pour une traçabilité complète bout en
            // bout. Rien n'est assigné à medecin@/infirmier@, pour que le
            // contrôle "accès vide pour un rôle sans droit qualité" reste
            // sans ambiguïté.

            // A — ouverte, non assignée : visible uniquement par
            // reclamations.manage_all tant qu'elle n'est pas assignée.
            Complaint::create([
                'structure_id' => $structure->id,
                'patient_id' => $patients[0]->id,
                'gestionnaire_id' => null,
                'motif' => "Délai d'attente excessif",
                'description' => "Le patient signale plus de deux heures d'attente en salle de consultation, sans information sur le retard.",
                'service_concerne' => 'Consultation',
                'statut' => 'ouverte',
            ]);

            // B — en_cours, assignée à secretaire@, 1 réponse.
            $complaintB = tap(Complaint::create([
                'structure_id' => $structure->id,
                'patient_id' => $patients[1]->id,
                'gestionnaire_id' => $secretaireUser->id,
                'motif' => 'Erreur de facturation',
                'description' => 'Le patient conteste le montant facturé, qui ne correspond pas au devis initialement communiqué.',
                'service_concerne' => 'Facturation',
                'statut' => 'en_cours',
            ]), fn ($c) => $c->forceFill(['created_at' => now()->subDays(4), 'updated_at' => now()->subDays(4)])->save());

            tap($complaintB->responses()->create([
                'auteur_id' => $secretaireUser->id,
                'message' => 'Réclamation prise en charge, vérification du devis et de la facture en cours auprès du service comptabilité.',
            ]), fn ($r) => $r->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save());

            // C — resolue, assignée à secretaire@, 2 réponses, resolved_at posé.
            $complaintC = tap(Complaint::create([
                'structure_id' => $structure->id,
                'patient_id' => $patients[2]->id,
                'gestionnaire_id' => $secretaireUser->id,
                'motif' => 'Confidentialité',
                'description' => 'Le patient rapporte que des informations médicales ont été évoquées à voix haute dans un couloir fréquenté.',
                'service_concerne' => 'Hospitalisation',
                'statut' => 'resolue',
                'resolved_at' => now()->subDays(3),
            ]), fn ($c) => $c->forceFill(['created_at' => now()->subDays(8), 'updated_at' => now()->subDays(3)])->save());

            tap($complaintC->responses()->create([
                'auteur_id' => $secretaireUser->id,
                'message' => 'Réclamation transmise au cadre de santé du service pour rappel des consignes de confidentialité.',
            ]), fn ($r) => $r->forceFill(['created_at' => now()->subDays(6), 'updated_at' => now()->subDays(6)])->save());
            tap($complaintC->responses()->create([
                'auteur_id' => $secretaireUser->id,
                'message' => "Rappel effectué auprès de l'équipe. Le patient a été informé des mesures prises et se dit satisfait du suivi.",
            ]), fn ($r) => $r->forceFill(['created_at' => now()->subDays(4), 'updated_at' => now()->subDays(4)])->save());

            // D — close, assignée à direction@, 2 réponses, resolved_at/closed_at posés.
            $complaintD = tap(Complaint::create([
                'structure_id' => $structure->id,
                'patient_id' => $patients[3]->id,
                'gestionnaire_id' => $directionUser->id,
                'motif' => 'Qualité des repas',
                'description' => 'Le patient hospitalisé signale des plateaux-repas froids et un manque de variété sur plusieurs jours consécutifs.',
                'service_concerne' => 'Hospitalisation',
                'statut' => 'close',
                'resolved_at' => now()->subDays(10),
                'closed_at' => now()->subDays(8),
            ]), fn ($c) => $c->forceFill(['created_at' => now()->subDays(15), 'updated_at' => now()->subDays(8)])->save());

            tap($complaintD->responses()->create([
                'auteur_id' => $directionUser->id,
                'message' => 'Signalement transmis au prestataire de restauration, audit de la chaîne du froid demandé.',
            ]), fn ($r) => $r->forceFill(['created_at' => now()->subDays(13), 'updated_at' => now()->subDays(13)])->save());
            tap($complaintD->responses()->create([
                'auteur_id' => $directionUser->id,
                'message' => "Correctifs appliqués par le prestataire et validés lors d'un contrôle qualité. Réclamation clôturée.",
            ]), fn ($r) => $r->forceFill(['created_at' => now()->subDays(11), 'updated_at' => now()->subDays(11)])->save());
        }

        if ($conventionPrivee && $conventionIpm && $patients->count() >= 2
            && PatientInsuranceCoverage::query()->where('structure_id', $structure->id)->doesntExist()) {
            PatientInsuranceCoverage::create([
                'structure_id' => $structure->id,
                'patient_id' => $patients[0]->id,
                'insurance_convention_id' => $conventionPrivee->id,
                'numero_adherent' => 'NSIA-'.str_pad((string) $patients[0]->id, 6, '0', STR_PAD_LEFT),
                'beneficiaire_type' => 'assure_principal',
                'date_debut' => now()->subYear()->toDateString(),
                'date_fin' => null,
                'actif' => true,
            ]);

            PatientInsuranceCoverage::create([
                'structure_id' => $structure->id,
                'patient_id' => $patients[1]->id,
                'insurance_convention_id' => $conventionIpm->id,
                'numero_adherent' => 'IPM-'.str_pad((string) $patients[1]->id, 6, '0', STR_PAD_LEFT),
                'beneficiaire_type' => 'ayant_droit',
                'date_debut' => now()->subYear()->toDateString(),
                'date_fin' => null,
                'actif' => true,
            ]);
        }

        $medecinUser = User::query()->where('email', 'medecin@sainte-marie.demo')->first();
        $caissierUser = User::query()->where('email', 'caissier@sainte-marie.demo')->first();

        if ($medecinUser && $patients->count() >= 3
            && Consultation::query()->where('structure_id', $structure->id)->where('reason', 'Consultation de suivi')->doesntExist()) {
            $billingService = app(BillingService::class);

            // BelongsToTenant only auto-fills structure_id from the
            // authenticated user (TenantScope::currentStructureId()) — a
            // seeder run has no HTTP-authenticated user, so BillingService's
            // internal BillableItem::create() would otherwise violate the
            // non-nullable structure_id column. Impersonate the demo
            // practitioner for the duration of this block only, matching
            // what a real request would resolve.
            Auth::guard('sanctum')->setUser($medecinUser);

            foreach ($patients->take(3) as $index => $patient) {
                $consultation = Consultation::create([
                    'structure_id' => $structure->id,
                    'patient_id' => $patient->id,
                    'practitioner_id' => $medecinUser->id,
                    'site_id' => $primarySite->id,
                    'reason' => 'Consultation de suivi',
                    'status' => 'terminee',
                    'closed_at' => now()->subDays(5 + $index * 20),
                ]);

                // Déclenche le vrai flux de facturation automatique
                // (App\Domain\Shared\Billing\Billable), au lieu de fabriquer
                // des BillableItem à la main : produit des lignes
                // indiscernables de celles générées par le module clinique réel.
                $billingService->recordService($consultation);
            }
        }

        if ($caissierUser && $patients->count() >= 8
            && CashSession::query()->where('structure_id', $structure->id)->doesntExist()) {
            $coverageService = app(InsuranceCoverageService::class);

            // Session de caisse historique, déjà fermée : le compte caissier
            // de démo ne doit garder aucune session ouverte, pour que le
            // parcours "ouverture de session" parte d'un état propre.
            $historicalSession = CashSession::create([
                'structure_id' => $structure->id,
                'site_id' => $primarySite->id,
                'caissier_id' => $caissierUser->id,
                'montant_ouverture' => 50000,
                'montant_cloture' => 64500,
                'ecart' => -500,
                'ouverte_le' => now()->subDays(10)->setTime(8, 0),
                'fermee_le' => now()->subDays(10)->setTime(18, 0),
                'statut' => 'fermee',
            ]);

            // Facture 1 : émise récente (tranche 0-30j), prestation réelle
            // avec répartition assurance NSIA (taux 70 %, sans plafond).
            $item1 = BillableItem::where('patient_id', $patients[0]->id)->where('billable_type', Consultation::class)->where('statut', 'a_facturer')->latest('id')->first();
            if ($item1) {
                $date1 = now()->subDays(5);
                $split1 = $coverageService->computeSplit($patients[0], (float) $item1->montant_total, $item1->categorie, $date1);

                $invoice1 = Invoice::create([
                    'structure_id' => $structure->id,
                    'site_id' => $primarySite->id,
                    'patient_id' => $patients[0]->id,
                    'insurance_convention_id' => $split1['convention']?->id,
                    'date_emission' => $date1->toDateString(),
                    'montant_total' => $item1->montant_total,
                    'montant_part_patient' => $split1['montant_patient'],
                    'montant_part_assurance' => $split1['montant_assurance'],
                    'statut' => 'emise',
                ]);

                $invoice1->items()->create([
                    'billable_item_id' => $item1->id,
                    'libelle' => $item1->libelle,
                    'categorie' => $item1->categorie,
                    'quantite' => $item1->quantite,
                    'prix_unitaire' => $item1->prix_unitaire,
                    'montant_total' => $item1->montant_total,
                    'taux_couverture_applique' => $split1['taux'],
                    'montant_assurance' => $split1['montant_assurance'],
                    'montant_patient' => $split1['montant_patient'],
                ]);

                $item1->update(['statut' => 'facturee']);
            }

            // Facture 2 : partiellement payée (tranche 31-60j), prestation
            // réelle avec répartition assurance IPM (taux 100 %).
            $item2 = BillableItem::where('patient_id', $patients[1]->id)->where('billable_type', Consultation::class)->where('statut', 'a_facturer')->latest('id')->first();
            if ($item2) {
                $date2 = now()->subDays(45);
                $split2 = $coverageService->computeSplit($patients[1], (float) $item2->montant_total, $item2->categorie, $date2);

                $invoice2 = Invoice::create([
                    'structure_id' => $structure->id,
                    'site_id' => $primarySite->id,
                    'patient_id' => $patients[1]->id,
                    'insurance_convention_id' => $split2['convention']?->id,
                    'date_emission' => $date2->toDateString(),
                    'montant_total' => $item2->montant_total,
                    'montant_part_patient' => $split2['montant_patient'],
                    'montant_part_assurance' => $split2['montant_assurance'],
                    'statut' => 'partiellement_payee',
                ]);

                $invoice2->items()->create([
                    'billable_item_id' => $item2->id,
                    'libelle' => $item2->libelle,
                    'categorie' => $item2->categorie,
                    'quantite' => $item2->quantite,
                    'prix_unitaire' => $item2->prix_unitaire,
                    'montant_total' => $item2->montant_total,
                    'taux_couverture_applique' => $split2['taux'],
                    'montant_assurance' => $split2['montant_assurance'],
                    'montant_patient' => $split2['montant_patient'],
                ]);

                $item2->update(['statut' => 'facturee']);

                Payment::create([
                    'structure_id' => $structure->id,
                    'site_id' => $primarySite->id,
                    'invoice_id' => $invoice2->id,
                    'cash_session_id' => null,
                    'caissier_id' => $caissierUser->id,
                    'mode_paiement' => 'carte',
                    'reference_transaction' => null,
                    'statut_mobile_money' => null,
                    'montant' => 8000,
                    'paid_at' => now()->subDays(40),
                ]);
            }

            // Facture 3 : émise, sans assurance (tranche 61-90j).
            $invoice3 = Invoice::create([
                'structure_id' => $structure->id,
                'site_id' => $primarySite->id,
                'patient_id' => $patients[3]->id,
                'date_emission' => now()->subDays(75)->toDateString(),
                'montant_total' => 20000,
                'montant_part_patient' => 20000,
                'montant_part_assurance' => 0,
                'statut' => 'emise',
            ]);
            $invoice3->items()->create([
                'libelle' => 'Consultation générale',
                'categorie' => 'consultation',
                'quantite' => 1,
                'prix_unitaire' => 20000,
                'montant_total' => 20000,
                'taux_couverture_applique' => 0,
                'montant_assurance' => 0,
                'montant_patient' => 20000,
            ]);

            // Facture 4 : émise, sans assurance (tranche 90+j).
            $invoice4 = Invoice::create([
                'structure_id' => $structure->id,
                'site_id' => $primarySite->id,
                'patient_id' => $patients[4]->id,
                'date_emission' => now()->subDays(120)->toDateString(),
                'montant_total' => 30000,
                'montant_part_patient' => 30000,
                'montant_part_assurance' => 0,
                'statut' => 'emise',
            ]);
            $invoice4->items()->create([
                'libelle' => 'Bilan biologique standard',
                'categorie' => 'laboratoire',
                'quantite' => 1,
                'prix_unitaire' => 30000,
                'montant_total' => 30000,
                'taux_couverture_applique' => 0,
                'montant_assurance' => 0,
                'montant_patient' => 30000,
            ]);

            // Facture 5 : intégralement payée, prestation réelle sans
            // assurance, réglée en espèces via la session historique.
            $item5 = BillableItem::where('patient_id', $patients[2]->id)->where('billable_type', Consultation::class)->where('statut', 'a_facturer')->latest('id')->first();
            if ($item5) {
                $date5 = now()->subDays(10);
                $split5 = $coverageService->computeSplit($patients[2], (float) $item5->montant_total, $item5->categorie, $date5);

                $invoice5 = Invoice::create([
                    'structure_id' => $structure->id,
                    'site_id' => $primarySite->id,
                    'patient_id' => $patients[2]->id,
                    'insurance_convention_id' => $split5['convention']?->id,
                    'date_emission' => $date5->toDateString(),
                    'montant_total' => $item5->montant_total,
                    'montant_part_patient' => $split5['montant_patient'],
                    'montant_part_assurance' => $split5['montant_assurance'],
                    'statut' => 'payee',
                ]);

                $invoice5->items()->create([
                    'billable_item_id' => $item5->id,
                    'libelle' => $item5->libelle,
                    'categorie' => $item5->categorie,
                    'quantite' => $item5->quantite,
                    'prix_unitaire' => $item5->prix_unitaire,
                    'montant_total' => $item5->montant_total,
                    'taux_couverture_applique' => $split5['taux'],
                    'montant_assurance' => $split5['montant_assurance'],
                    'montant_patient' => $split5['montant_patient'],
                ]);

                $item5->update(['statut' => 'facturee']);

                Payment::create([
                    'structure_id' => $structure->id,
                    'site_id' => $primarySite->id,
                    'invoice_id' => $invoice5->id,
                    'cash_session_id' => $historicalSession->id,
                    'caissier_id' => $caissierUser->id,
                    'mode_paiement' => 'especes',
                    'reference_transaction' => null,
                    'statut_mobile_money' => null,
                    'montant' => $item5->montant_total,
                    'paid_at' => $date5->copy()->setTime(12, 0),
                ]);
            }

            // Facture 6 : annulée.
            $invoice6 = Invoice::create([
                'structure_id' => $structure->id,
                'site_id' => $primarySite->id,
                'patient_id' => $patients[5]->id,
                'date_emission' => now()->subDays(20)->toDateString(),
                'montant_total' => 12000,
                'montant_part_patient' => 12000,
                'montant_part_assurance' => 0,
                'statut' => 'annulee',
            ]);
            $invoice6->items()->create([
                'libelle' => 'Consultation générale',
                'categorie' => 'consultation',
                'quantite' => 1,
                'prix_unitaire' => 12000,
                'montant_total' => 12000,
                'taux_couverture_applique' => 0,
                'montant_assurance' => 0,
                'montant_patient' => 12000,
            ]);

            // Facture 7 : brouillon.
            $invoice7 = Invoice::create([
                'structure_id' => $structure->id,
                'site_id' => $primarySite->id,
                'patient_id' => $patients[6]->id,
                'date_emission' => now()->toDateString(),
                'montant_total' => 18000,
                'montant_part_patient' => 18000,
                'montant_part_assurance' => 0,
                'statut' => 'brouillon',
            ]);
            $invoice7->items()->create([
                'libelle' => "Examen d'imagerie standard",
                'categorie' => 'imagerie',
                'quantite' => 1,
                'prix_unitaire' => 18000,
                'montant_total' => 18000,
                'taux_couverture_applique' => 0,
                'montant_assurance' => 0,
                'montant_patient' => 18000,
            ]);

            // Devis prêt pour conversion en facture.
            $quote = Quote::create([
                'structure_id' => $structure->id,
                'site_id' => $primarySite->id,
                'patient_id' => $patients[7]->id,
                'date_emission' => now()->subDays(2)->toDateString(),
                'montant_total' => 45000,
                'statut' => 'emis',
            ]);
            $quote->items()->create([
                'libelle' => 'Consultation cardiologie',
                'categorie' => 'consultation',
                'quantite' => 1,
                'prix_unitaire' => 20000,
                'montant_total' => 20000,
            ]);
            $quote->items()->create([
                'libelle' => 'Bilan biologique standard',
                'categorie' => 'laboratoire',
                'quantite' => 1,
                'prix_unitaire' => 25000,
                'montant_total' => 25000,
            ]);
        }
    }
}
