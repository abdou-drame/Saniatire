<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * visible_patient distingue une réponse destinée au patient (par défaut :
     * c'est le sens historique de "répondre à une réclamation") d'une note
     * interne entre membres du personnel, jamais exposée sur le portail
     * patient. Défaut à true pour ne pas changer rétroactivement le sens des
     * réponses déjà enregistrées avant l'existence du portail patient.
     */
    public function up(): void
    {
        Schema::table('complaint_responses', function (Blueprint $table) {
            $table->boolean('visible_patient')->default(true)->after('message');
        });
    }

    public function down(): void
    {
        Schema::table('complaint_responses', function (Blueprint $table) {
            $table->dropColumn('visible_patient');
        });
    }
};
