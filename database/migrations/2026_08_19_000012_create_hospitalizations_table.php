<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitalizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bed_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attending_physician_id')->constrained('users')->cascadeOnDelete();

            $table->dateTime('admitted_at');
            $table->text('admission_reason');
            $table->dateTime('discharged_at')->nullable();
            $table->text('discharge_summary')->nullable();
            $table->enum('status', ['en_cours', 'sorti', 'transfere'])->default('en_cours');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['structure_id', 'patient_id', 'status'], 'hospitalizations_patient_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitalizations');
    }
};
