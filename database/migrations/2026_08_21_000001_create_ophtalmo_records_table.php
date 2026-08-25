<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simplest specialty shape: one row per exam, no child tables — the
     * étape 4a audit's extensibility proof used this exact table as its
     * illustrative sketch.
     */
    public function up(): void
    {
        Schema::create('ophtalmo_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();

            $table->string('visual_acuity_od_uncorrected')->nullable();
            $table->string('visual_acuity_od_corrected')->nullable();
            $table->string('visual_acuity_og_uncorrected')->nullable();
            $table->string('visual_acuity_og_corrected')->nullable();
            $table->unsignedSmallInteger('intraocular_pressure_od')->nullable();
            $table->unsignedSmallInteger('intraocular_pressure_og')->nullable();
            $table->decimal('refraction_od_sphere', 4, 2)->nullable();
            $table->decimal('refraction_od_cylinder', 4, 2)->nullable();
            $table->unsignedSmallInteger('refraction_od_axis')->nullable();
            $table->decimal('refraction_og_sphere', 4, 2)->nullable();
            $table->decimal('refraction_og_cylinder', 4, 2)->nullable();
            $table->unsignedSmallInteger('refraction_og_axis')->nullable();
            $table->text('fundus_exam')->nullable();
            $table->text('optical_correction_prescription')->nullable();
            $table->date('examined_at');

            $table->timestamps();

            $table->index(['structure_id', 'patient_id'], 'ophtalmo_records_patient_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ophtalmo_records');
    }
};
