<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per tooth (FDI two-digit numbering, e.g. "11", "26", "84").
     * The unique constraint + updateOrCreate in the controller is what
     * lets a single tooth be updated without touching the others — same
     * pattern as surgical_checklists' unique(procedure_id, step).
     */
    public function up(): void
    {
        Schema::create('dental_tooth_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dental_chart_id')->constrained()->cascadeOnDelete();

            $table->string('tooth_fdi', 2);
            $table->enum('status', ['saine', 'cariee', 'obturee', 'extraite', 'couronnee', 'absente', 'implant', 'bridge'])->default('saine');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['dental_chart_id', 'tooth_fdi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_tooth_states');
    }
};
