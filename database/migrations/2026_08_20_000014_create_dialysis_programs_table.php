<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The recurring prescription (frequency, target dry weight, vascular
     * access) — one per patient's dialysis course. Individual sessions
     * are recorded separately on dialysis_sessions.
     */
    public function up(): void
    {
        Schema::create('dialysis_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();

            $table->unsignedTinyInteger('frequency_per_week');
            $table->decimal('dry_weight_kg', 5, 2);
            $table->enum('vascular_access_type', ['fistule', 'catheter', 'greffon']);
            $table->enum('vascular_access_status', ['fonctionnel', 'complique'])->default('fonctionnel');
            $table->enum('status', ['actif', 'arrete'])->default('actif');
            $table->date('started_at');

            $table->timestamps();

            $table->index(['structure_id', 'patient_id'], 'dialysis_programs_patient_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dialysis_programs');
    }
};
