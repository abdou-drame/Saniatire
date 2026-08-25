<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();

            // Placeholder for a future shared generic-drug/DCI catalog table
            // enabling cross-structure statistics — no such table exists yet,
            // so this is a plain nullable reference code, not an FK.
            $table->string('generic_catalog_ref')->nullable();

            $table->string('nom_commercial');
            $table->string('dci');
            $table->string('forme_galenique');
            $table->string('dosage')->nullable();
            $table->enum('categorie', ['medicament', 'consommable', 'dispositif_medical']);
            $table->string('unite_vente');
            $table->boolean('actif')->default(true);

            $table->timestamps();

            $table->index(['structure_id', 'categorie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
