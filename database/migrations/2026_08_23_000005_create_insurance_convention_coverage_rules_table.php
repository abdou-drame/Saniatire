<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per (convention, categorie) — categorie shares the same
     * vocabulary as billable_items.categorie ('consultation', 'laboratoire',
     * 'imagerie', ...). taux_couverture is the % reimbursed by the
     * organisme ; plafond_montant caps the reimbursed amount regardless of
     * taux ; exclu=true means the category is never covered even if a rate
     * happens to be set. See InsuranceCoverageService::computeSplit().
     */
    public function up(): void
    {
        Schema::create('insurance_convention_coverage_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_convention_id')->constrained()->cascadeOnDelete();

            $table->string('categorie');
            $table->decimal('taux_couverture', 5, 2)->default(0);
            $table->decimal('plafond_montant', 10, 2)->nullable();
            $table->boolean('exclu')->default(false);

            $table->timestamps();

            $table->unique(['insurance_convention_id', 'categorie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_convention_coverage_rules');
    }
};
