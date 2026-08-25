<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mobile money (Wave, Orange Money, Free Money) is deliberately stubbed
     * here, same doctrine as billing_status/generic catalog refs in étape
     * 5a : `reference_transaction` (the operator's transaction id) and
     * `statut_mobile_money` (pending/confirmed/failed) are the only two
     * fields a future webhook or reconciliation job would need to touch to
     * plug in a real integration — no schema change required then.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('caissier_id')->constrained('users')->cascadeOnDelete();

            $table->enum('mode_paiement', ['especes', 'carte', 'virement', 'mobile_money']);
            $table->string('reference_transaction')->nullable();
            $table->enum('statut_mobile_money', ['pending', 'confirmed', 'failed'])->nullable();
            $table->decimal('montant', 12, 2);
            $table->string('numero_recu')->nullable();
            $table->dateTime('paid_at');

            $table->timestamps();

            $table->index(['invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
