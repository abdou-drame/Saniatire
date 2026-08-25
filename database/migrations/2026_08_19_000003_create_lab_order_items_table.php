<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per analysis requested on a lab_order. Tenant isolation is
     * inherited through lab_order_id (same pattern as consultation_diagnoses
     * under consultations) — no structure_id of its own.
     */
    public function up(): void
    {
        Schema::create('lab_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loinc_code_id')->nullable()->constrained('loinc_codes')->nullOnDelete();

            $table->enum('status', ['demande', 'prelevee', 'annulee'])->default('demande');

            $table->timestamps();

            $table->unique(['lab_order_id', 'loinc_code_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_order_items');
    }
};
