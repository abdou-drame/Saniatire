<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pas de structure_id/BelongsToTenant ici : la portée tenant est déjà
     * garantie par complaint_id (une réponse n'existe jamais sans sa
     * réclamation, elle-même bornée à sa structure) — même logique que
     * complaint_responses est un historique d'échanges, pas une ressource
     * interrogée directement par structure.
     */
    public function up(): void
    {
        Schema::create('complaint_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complaint_id')->constrained('complaints')->cascadeOnDelete();
            $table->foreignId('auteur_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_responses');
    }
};
