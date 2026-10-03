<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiement des abonnements par mobile money (DexPay). Une ligne par
 * session de paiement, créée en `en_attente` AVANT l'appel réseau : la
 * tentative reste tracée même si DexPay ne répond pas. Le webhook
 * `checkout.completed` la passe en `complete` et y rattache la période
 * d'abonnement créée ; transaction_id et subscription_id uniques servent
 * de garde-fou d'idempotence en plus du verrou du webhook.
 *
 * subscriptions.billing_period : périodicité facturée (monthly/annual),
 * renseignée pour les périodes payées en ligne. Nullable : les périodes
 * saisies à la main n'en ont pas (voir SubscriptionRenewal pour la
 * déduction à partir de leur durée).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('period');
            $table->string('reference')->unique();
            $table->string('checkout_session_id')->nullable()->index();
            $table->string('transaction_id')->nullable()->unique();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3);
            $table->string('status');
            $table->string('provider');
            $table->text('payment_url')->nullable();
            $table->json('raw_payload')->nullable();
            // "platform_admin:{id}" ou "structure_admin:{id}" : deux
            // modèles d'acteurs différents, pas de clé étrangère possible.
            $table->string('initiated_by');
            $table->foreignId('subscription_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['structure_id', 'created_at']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_period')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('billing_period');
        });

        Schema::dropIfExists('payment_transactions');
    }
};
