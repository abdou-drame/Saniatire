<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occupational_health_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();

            $table->enum('visit_type', ['embauche', 'periodique', 'reprise', 'demande']);
            $table->enum('fitness_status', ['apte', 'apte_avec_reserves', 'inapte']);
            $table->text('restrictions')->nullable();
            $table->json('risk_exposures')->nullable();
            $table->date('visit_date');
            $table->date('next_visit_due_at')->nullable();

            $table->timestamps();

            $table->index(['structure_id', 'patient_id'], 'occupational_health_records_patient_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occupational_health_records');
    }
};
