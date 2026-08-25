<?php

namespace Database\Seeders;

use App\Domain\Achats\Models\ApprovalRule;
use App\Domain\Achats\Models\Supplier;
use App\Domain\Biomedical\Models\BiomedicalEquipment;
use App\Domain\Biomedical\Models\EquipmentMaintenance;
use App\Domain\Hospitalisation\Models\Bed;
use App\Domain\Hospitalisation\Models\Ward;
use App\Domain\Patient\Models\Patient;
use App\Domain\Patient\Models\PatientMedicalInfo;
use App\Domain\Pharmacie\Models\Product;
use App\Domain\Pharmacie\Models\ProductBatch;
use App\Domain\Pharmacie\Models\StockThreshold;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Illuminate\Database\Seeder;

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
    }
}
