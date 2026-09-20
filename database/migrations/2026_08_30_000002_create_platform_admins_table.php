<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administration plateforme : table dédiée, guard `platform` séparé (voir
 * config/auth.php), sur le même modèle que patients/external_prescribers.
 * Volontairement hors de toute structure — pas de structure_id, pas de
 * BelongsToTenant — puisque c'est précisément l'acteur qui existe en
 * dehors du système d'isolation par structure. Aucune route d'inscription
 * publique : les comptes sont créés uniquement via la commande Artisan
 * `platform:create-admin` (voir App\Console\Commands\CreatePlatformAdminCommand).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_admins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_admins');
    }
};
