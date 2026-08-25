<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kine_programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();

            $table->string('affected_area');
            $table->string('initial_range_of_motion')->nullable();
            $table->unsignedTinyInteger('initial_pain_scale')->nullable();
            $table->text('objectives')->nullable();
            $table->enum('status', ['actif', 'termine'])->default('actif');
            $table->date('started_at');

            $table->timestamps();

            $table->index(['structure_id', 'patient_id'], 'kine_programs_patient_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kine_programs');
    }
};
