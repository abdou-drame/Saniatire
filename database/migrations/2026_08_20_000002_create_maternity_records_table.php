<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * estimated_delivery_date (DPA) is computed automatically from
     * last_menstrual_period_date (DDR) via Naegele's rule (+280 days) in
     * MaternityRecord's saving hook — same pattern as Consultation::$bmi.
     */
    public function up(): void
    {
        Schema::create('maternity_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->unique()->constrained('consultations')->nullOnDelete();

            $table->date('last_menstrual_period_date');
            $table->date('estimated_delivery_date');
            $table->enum('status', ['suivi', 'accouchee'])->default('suivi');

            $table->timestamps();

            $table->index(['structure_id', 'patient_id'], 'maternity_records_patient_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maternity_records');
    }
};
