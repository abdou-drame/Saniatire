<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A billable_item is the automatic, generic trace of one realized
     * prestation (cahier des charges §48 : facturation automatique, pas de
     * ressaisie). It is created by BillingService::recordService() the
     * moment a clinical module marks its own act as complete — see
     * App\Domain\Shared\Billing\Billable / BillingService. `billable_type`
     * / `billable_id` point back to the source record (Consultation,
     * LabOrder, ImagingOrder, Hospitalization, SurgicalProcedure,
     * DialysisSession, KineSession, ...) without any of those modules
     * needing to know about invoicing.
     */
    public function up(): void
    {
        Schema::create('billable_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->morphs('billable');

            $table->string('categorie');
            $table->string('code_prestation');
            $table->string('libelle');
            $table->unsignedInteger('quantite')->default(1);
            $table->decimal('prix_unitaire', 10, 2);
            $table->decimal('montant_total', 10, 2);
            $table->enum('statut', ['a_facturer', 'facturee', 'annulee'])->default('a_facturer');

            $table->timestamps();

            $table->index(['patient_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billable_items');
    }
};
