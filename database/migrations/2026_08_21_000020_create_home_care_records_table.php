<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * intervention_address is stored separately from the patient's own
     * address since a home-care visit's site can legitimately differ
     * (temporary residence, family member's home, ...).
     */
    public function up(): void
    {
        Schema::create('home_care_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();

            $table->string('intervention_address');
            $table->string('care_type');

            $table->timestamps();

            $table->index(['structure_id', 'patient_id'], 'home_care_records_patient_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_care_records');
    }
};
