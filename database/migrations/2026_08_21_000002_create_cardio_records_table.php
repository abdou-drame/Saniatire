<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cardio_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();

            $table->json('risk_factors')->nullable();
            $table->text('current_treatment')->nullable();
            $table->date('examined_at');

            $table->timestamps();

            $table->index(['structure_id', 'patient_id'], 'cardio_records_patient_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cardio_records');
    }
};
