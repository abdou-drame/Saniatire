<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two distinct validators are tracked (technical then biological), each
     * with their own user/timestamp pair — the workflow-blocking rule that
     * biological validation cannot happen before technical validation is
     * enforced in LabResultController, not at the DB level, so it produces
     * a clear 422 rather than a constraint violation.
     */
    public function up(): void
    {
        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_sample_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_order_item_id')->constrained()->cascadeOnDelete();

            $table->string('value')->nullable();
            $table->string('unit')->nullable();
            $table->decimal('reference_min', 10, 3)->nullable();
            $table->decimal('reference_max', 10, 3)->nullable();
            $table->enum('interpretation', ['normal', 'anormal', 'critique'])->nullable();

            $table->enum('status', [
                'en_cours', 'validation_technique_attente', 'validation_technique_faite',
                'validation_biologique_attente', 'valide', 'transmis',
            ])->default('validation_technique_attente');

            $table->foreignId('technical_validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('technical_validated_at')->nullable();
            $table->foreignId('biological_validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('biological_validated_at')->nullable();

            $table->timestamps();

            $table->unique(['lab_sample_id', 'lab_order_item_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_results');
    }
};
