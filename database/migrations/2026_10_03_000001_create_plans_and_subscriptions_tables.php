<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Formules et abonnements Saliha Health, gérés manuellement par
 * l'administration plateforme (aucune intégration de paiement). La grille
 * tarifaire est insérée ici plutôt que dans un seeder : en production les
 * seeders ne tournent qu'au premier déploiement, alors que les migrations
 * s'appliquent à chaque déploiement (deploy:migrate).
 *
 * Pas de table plan_modules : les modules premium sont vendus à la carte,
 * indépendamment de la formule (voir StructureModule). Une ligne
 * `subscriptions` par période ; l'état courant (actif / en grâce / lecture
 * seule) est calculé à la lecture, voir SubscriptionState.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            // Nullable : la formule Enterprise est sur devis.
            $table->unsignedBigInteger('monthly_price_fcfa')->nullable();
            $table->unsignedBigInteger('annual_price_fcfa')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('platform_admins')->nullOnDelete();
            $table->timestamps();
            $table->index(['structure_id', 'starts_at']);
        });

        $now = now();
        DB::table('plans')->insert([
            ['code' => 'start', 'name' => 'SALIHA Start', 'monthly_price_fcfa' => 15000, 'annual_price_fcfa' => 150000],
            ['code' => 'pro', 'name' => 'SALIHA Pro', 'monthly_price_fcfa' => 35000, 'annual_price_fcfa' => 350000],
            ['code' => 'business', 'name' => 'SALIHA Business', 'monthly_price_fcfa' => 75000, 'annual_price_fcfa' => 750000],
            ['code' => 'premium', 'name' => 'SALIHA Premium', 'monthly_price_fcfa' => 150000, 'annual_price_fcfa' => 1500000],
            ['code' => 'enterprise', 'name' => 'SALIHA Enterprise', 'monthly_price_fcfa' => null, 'annual_price_fcfa' => null],
        ]);
        DB::table('plans')->update(['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
