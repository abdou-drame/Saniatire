<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();

            $table->morphs('notifiable');
            // Liste ordonnée de canaux préférés, ex. ["whatsapp","email"].
            // Absence de ligne pour un notifiable = défaut
            // config('notifications.default_channels') appliqué en code.
            $table->json('canaux');

            $table->timestamps();

            $table->unique(['notifiable_type', 'notifiable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
