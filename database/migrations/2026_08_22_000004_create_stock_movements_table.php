<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('destination_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->enum('type', ['entree', 'sortie', 'ajustement', 'transfert']);
            $table->integer('quantite');
            $table->string('motif')->nullable();

            // Generic hook point for tying a stock exit to whatever clinical
            // record triggered the dispensation (consultation, hospitalization,
            // chemo cycle, ...) — no clinical module carries a pre-built FK for
            // this, so a nullable polymorphic pair is the extension point.
            $table->string('dispensed_for_type')->nullable();
            $table->unsignedBigInteger('dispensed_for_id')->nullable();

            $table->timestamps();

            $table->index(['product_batch_id', 'type']);
            $table->index(['dispensed_for_type', 'dispensed_for_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
