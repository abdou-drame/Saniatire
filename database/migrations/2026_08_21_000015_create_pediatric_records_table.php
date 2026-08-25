<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thin container — growth measurements, vaccinations and development
     * observations are each their own time series (see the three child
     * tables) rather than columns here.
     */
    public function up(): void
    {
        Schema::create('pediatric_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();

            $table->timestamps();

            $table->index(['structure_id', 'patient_id'], 'pediatric_records_patient_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pediatric_records');
    }
};
