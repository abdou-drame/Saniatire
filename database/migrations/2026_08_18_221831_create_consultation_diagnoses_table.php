<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a consultation to an ICD code with a frozen snapshot of the
     * code/label/version used at coding time (icd_code_id is kept only for
     * navigation). If icd_codes is later edited or the referentiel moves
     * from CIM-10 to CIM-11, this row — and therefore the patient's
     * history — never changes retroactively.
     */
    public function up(): void
    {
        Schema::create('consultation_diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('icd_code_id')->nullable()->constrained('icd_codes')->nullOnDelete();

            $table->string('code_snapshot');
            $table->string('label_snapshot');
            $table->enum('version_snapshot', ['CIM-10', 'CIM-11']);

            $table->enum('type', ['principal', 'secondaire']);
            $table->enum('status', ['provisoire', 'confirme'])->default('provisoire');

            $table->timestamps();

            $table->index(['consultation_id', 'type'], 'consultation_diagnoses_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consultation_diagnoses');
    }
};
