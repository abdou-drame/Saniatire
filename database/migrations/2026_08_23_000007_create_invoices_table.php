<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            // Snapshot of the convention used to compute the split at
            // generation time, kept even if the patient's coverage changes
            // later.
            $table->foreignId('insurance_convention_id')->nullable()->constrained()->nullOnDelete();

            $table->string('numero')->nullable();
            $table->date('date_emission');
            $table->decimal('montant_total', 12, 2)->default(0);
            $table->decimal('montant_part_patient', 12, 2)->default(0);
            $table->decimal('montant_part_assurance', 12, 2)->default(0);
            $table->enum('statut', ['brouillon', 'emise', 'partiellement_payee', 'payee', 'annulee'])->default('brouillon');

            $table->timestamps();

            $table->index(['patient_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
