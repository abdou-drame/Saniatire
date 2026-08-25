<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_insurance_coverages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_convention_id')->constrained()->cascadeOnDelete();

            $table->string('numero_adherent');
            $table->enum('beneficiaire_type', ['assure_principal', 'ayant_droit']);
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->boolean('actif')->default(true);

            $table->timestamps();

            $table->index(['patient_id', 'actif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_insurance_coverages');
    }
};
