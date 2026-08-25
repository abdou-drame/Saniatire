<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vitals use fixed columns for the standard clinical parameter set
     * (always present, always queryable/aggregatable regardless of
     * specialty) plus one `extra_vitals` JSON column for specialty-specific
     * parameters, so a new one can be added without a migration. See the
     * Consultation model docblock for the full rationale.
     */
    public function up(): void
    {
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('practitioner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();

            $table->string('reason');
            $table->text('history_of_illness')->nullable();

            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->decimal('height_cm', 5, 1)->nullable();
            $table->decimal('bmi', 5, 2)->nullable();
            $table->decimal('temperature_c', 4, 1)->nullable();
            $table->unsignedSmallInteger('blood_pressure_systolic')->nullable();
            $table->unsignedSmallInteger('blood_pressure_diastolic')->nullable();
            $table->unsignedSmallInteger('heart_rate')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->unsignedTinyInteger('spo2')->nullable();
            $table->decimal('glycemia', 5, 1)->nullable();
            $table->unsignedTinyInteger('pain_scale')->nullable();
            $table->json('extra_vitals')->nullable();

            $table->text('clinical_exam')->nullable();
            $table->text('recommendations')->nullable();
            $table->string('referral')->nullable();
            $table->date('follow_up_suggested_at')->nullable();

            $table->enum('status', ['en_cours', 'terminee'])->default('en_cours');
            $table->dateTime('closed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['structure_id', 'patient_id', 'status'], 'consultations_patient_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultations');
    }
};
