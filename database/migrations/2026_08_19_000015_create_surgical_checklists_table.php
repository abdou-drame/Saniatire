<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The unique(surgical_procedure_id, step) constraint is what makes the
     * "3 distinct steps" rule structural: a procedure can have at most one
     * row per step, so SurgicalProcedure::hasCompleteChecklist() can simply
     * count validated rows against the 3 required step names.
     */
    public function up(): void
    {
        Schema::create('surgical_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surgical_procedure_id')->constrained()->cascadeOnDelete();

            $table->enum('step', ['avant_anesthesie', 'avant_incision', 'avant_sortie_bloc']);
            $table->json('items');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('validated_at')->nullable();

            $table->timestamps();

            $table->unique(['surgical_procedure_id', 'step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('surgical_checklists');
    }
};
