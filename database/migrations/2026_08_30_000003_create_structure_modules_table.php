<?php

use App\Domain\Structure\Models\Structure;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cahier des charges §7 : "modules activés" par structure. Uniquement la
 * donnée et l'écran de gestion plateforme dans ce chantier — aucune lecture
 * de cette table n'est branchée ailleurs dans l'application pour l'instant
 * (pas d'activation/désactivation réelle de fonctionnalité).
 *
 * `module` reprend exactement RolePermissionSeeder::MODULES plutôt que
 * d'inventer une seconde taxonomie qui pourrait diverger de la première.
 * Backfill des structures déjà existantes (toutes actives par défaut) pour
 * que l'écran de gestion ne parte pas vide sur les données de démo/prod
 * déjà en place ; toute structure créée après coup via le flux plateforme
 * est initialisée de la même façon dans PlatformStructureController::store().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structure_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->string('module');
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
            $table->unique(['structure_id', 'module']);
        });

        $now = now();
        $structureIds = Structure::query()->pluck('id');

        foreach ($structureIds as $structureId) {
            DB::table('structure_modules')->insert(
                collect(RolePermissionSeeder::MODULES)->map(fn (string $module) => [
                    'structure_id' => $structureId,
                    'module' => $module,
                    'is_active' => true,
                    'activated_at' => $now,
                    'deactivated_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all()
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('structure_modules');
    }
};
