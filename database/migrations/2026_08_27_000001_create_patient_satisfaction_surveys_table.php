<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * prestation_type/prestation_id (morphs nullable) suivent la même
     * convention que billable_items.billable_type/_id — un lien optionnel
     * vers la prestation évaluée, sans imposer laquelle. `service` est un
     * champ libre distinct (ex. "laboratoire", "consultation") pour la
     * synthèse qualité "par service", même quand aucune prestation précise
     * n'est reliée.
     */
    public function up(): void
    {
        Schema::create('patient_satisfaction_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->nullableMorphs('prestation');
            $table->string('service')->nullable();
            $table->unsignedTinyInteger('note');
            $table->text('commentaire')->nullable();
            $table->date('date');
            $table->timestamps();

            $table->index(['structure_id', 'date'], 'patient_satisfaction_surveys_structure_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_satisfaction_surveys');
    }
};
