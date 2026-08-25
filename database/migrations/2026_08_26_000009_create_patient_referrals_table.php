<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pas de foreignId()->constrained() classique sur patient_id : le
     * patient référencé appartient à structure_origine_id, pas forcément à
     * la structure qui lit cette ligne — la contrainte FK reste valide
     * (patients.id existe toujours), seule la visibilité applicative est
     * bornée par ReferralVisibilityScope, pas par une FK.
     */
    public function up(): void
    {
        Schema::create('patient_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_origine_id')->constrained('structures')->cascadeOnDelete();
            $table->foreignId('site_origine_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->foreignId('structure_destination_id')->constrained('structures')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('praticien_referent_id')->constrained('users')->cascadeOnDelete();

            $table->text('motif');
            $table->enum('statut', ['envoye', 'accepte', 'refuse', 'complete'])->default('envoye');
            $table->text('compte_rendu_retour')->nullable();

            $table->timestamps();

            $table->index(['structure_origine_id', 'structure_destination_id'], 'patient_referrals_structures_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_referrals');
    }
};
