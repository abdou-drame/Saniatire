<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * lien_session est un placeholder texte (URL de salle) — aucune
     * intégration vidéo réelle n'est construite ici (cf. étape 7b §8).
     * Un vrai service (Twilio Video, Jitsi, Daily.co...) pourrait plus
     * tard générer et écrire l'URL de session dans cette même colonne
     * sans changement de schéma.
     */
    public function up(): void
    {
        Schema::create('teleconsultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('practitioner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();

            $table->enum('statut', ['planifiee', 'en_cours', 'terminee', 'annulee'])->default('planifiee');
            $table->string('lien_session')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();

            $table->index(['structure_id', 'patient_id', 'statut'], 'teleconsultations_patient_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teleconsultations');
    }
};
