<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surgical_procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hospitalization_id')->nullable()->constrained('hospitalizations')->nullOnDelete();
            $table->foreignId('surgeon_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('anesthesiologist_id')->constrained('users')->cascadeOnDelete();

            $table->string('operating_room');
            $table->string('procedure_type');
            $table->dateTime('scheduled_at');
            $table->dateTime('performed_at')->nullable();
            $table->enum('status', ['planifiee', 'en_cours', 'terminee', 'annulee'])->default('planifiee');

            $table->timestamps();
            $table->softDeletes();

            $table->index(['structure_id', 'patient_id', 'status'], 'surgical_procedures_patient_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surgical_procedures');
    }
};
