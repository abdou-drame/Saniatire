<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Highly sensitive data — access is deliberately narrower than the
     * other specialties (see RolePermissionSeeder::ROLE_PERMISSIONS: only
     * psychiatre/psychologue and directeur_medical get sante_mentale.*
     * permissions, unlike every other specialty which also grants
     * view/export to the non-clinical direction role).
     */
    public function up(): void
    {
        Schema::create('mental_health_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();

            $table->text('consultation_reason');
            $table->text('clinical_evaluation')->nullable();
            $table->text('ongoing_treatment')->nullable();

            $table->timestamps();

            $table->index(['structure_id', 'patient_id'], 'mental_health_records_patient_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mental_health_records');
    }
};
