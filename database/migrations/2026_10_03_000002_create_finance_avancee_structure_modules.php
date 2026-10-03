<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Livraison B : le module commercial `finance_avancee` remplace l'ancienne
 * clé `assurance` de structure_modules (ModuleCatalog). L'état de chaque
 * ligne `assurance` existante est recopié dans une ligne `finance_avancee`,
 * sans écraser une ligne déjà présente. Rien n'est supprimé : les anciennes
 * lignes (assurance, conges, clés du socle) restent en base et sont
 * simplement ignorées par ModuleCatalog.
 */
return new class extends Migration
{
    public function up(): void
    {
        $alreadyMigrated = DB::table('structure_modules')
            ->where('module', 'finance_avancee')
            ->pluck('structure_id');

        $rows = DB::table('structure_modules')
            ->where('module', 'assurance')
            ->whereNotIn('structure_id', $alreadyMigrated)
            ->get();

        $now = now();

        foreach ($rows as $row) {
            DB::table('structure_modules')->insert([
                'structure_id' => $row->structure_id,
                'module' => 'finance_avancee',
                'is_active' => $row->is_active,
                'activated_at' => $row->activated_at,
                'deactivated_at' => $row->deactivated_at,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Données métier recopiées : pas de retour arrière destructif.
    }
};
