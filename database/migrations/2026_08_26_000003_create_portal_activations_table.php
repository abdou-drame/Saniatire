<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le token d'activation en clair n'est jamais persisté — seul son hash
     * SHA-256 l'est, même patron que le broker de reset de mot de passe de
     * Laravel. activatable_type/_id est polymorphe : réutilisé à la fois
     * pour l'activation du portail patient et celle du portail prescripteur.
     */
    public function up(): void
    {
        Schema::create('portal_activations', function (Blueprint $table) {
            $table->id();
            $table->morphs('activatable');
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_activations');
    }
};
