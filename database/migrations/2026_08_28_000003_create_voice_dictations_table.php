<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Étape 9 §4 : structure uniquement — aucun service de transcription réel
 * branché. Voir app/Domain/Ai/README.md pour le point d'extension (Whisper
 * API ou équivalent) : brancher un job qui lit audio_path, appelle le
 * service, puis met à jour transcription/status, sans changer ce schéma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voice_dictations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('audio_path');
            $table->enum('status', ['en_attente', 'terminee', 'echouee'])->default('en_attente');
            $table->text('transcription')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voice_dictations');
    }
};
