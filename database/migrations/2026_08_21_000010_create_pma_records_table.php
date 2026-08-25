<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Highly sensitive data — access is deliberately narrower than the
     * other specialties (see RolePermissionSeeder::ROLE_PERMISSIONS: only
     * specialiste_pma and directeur_medical get pma.* permissions, unlike
     * every other specialty which also grants view/export to the
     * non-clinical direction role).
     */
    public function up(): void
    {
        Schema::create('pma_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();

            $table->text('fertility_history')->nullable();
            $table->text('exams_performed')->nullable();
            $table->enum('attempt_result', ['en_cours', 'positif', 'negatif'])->default('en_cours');

            $table->timestamps();

            $table->index(['structure_id', 'patient_id'], 'pma_records_patient_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pma_records');
    }
};
