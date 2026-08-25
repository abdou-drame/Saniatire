<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onco_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();

            $table->string('cancer_type');
            $table->string('stage_t')->nullable();
            $table->string('stage_n')->nullable();
            $table->string('stage_m')->nullable();
            $table->string('protocol_name')->nullable();
            $table->unsignedTinyInteger('treatment_line')->nullable();
            $table->date('diagnosed_at')->nullable();

            $table->timestamps();

            $table->index(['structure_id', 'patient_id'], 'onco_records_patient_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onco_records');
    }
};
