<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * gestionnaire_id est nullable : une réclamation existe dès son
     * ouverture (statut "ouverte"), avant toute assignation — l'assignation
     * (statut "en_cours") est ce qui la remplit. resolved_at/closed_at
     * tracent la traversée du workflow ouverte -> en_cours -> resolue ->
     * close (voir ComplaintController), en plus de l'historique automatique
     * via LogsActivity sur le modèle.
     */
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained('structures')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('gestionnaire_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('motif');
            $table->text('description');
            $table->string('service_concerne')->nullable();
            $table->enum('statut', ['ouverte', 'en_cours', 'resolue', 'close'])->default('ouverte');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();

            $table->index(['structure_id', 'statut'], 'complaints_structure_statut_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
