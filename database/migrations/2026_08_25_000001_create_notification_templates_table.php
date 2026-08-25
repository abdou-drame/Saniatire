<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            // Nullable: null = template par défaut global, utilisé quand la
            // structure n'a pas défini son propre template pour ce couple
            // (type_evenement, canal). Pas de BelongsToTenant::creating()
            // auto-fill souhaité ici pour les lignes globales — géré au
            // niveau modèle (voir NotificationTemplate).
            $table->foreignId('structure_id')->nullable()->constrained()->cascadeOnDelete();

            $table->string('type_evenement');
            $table->enum('canal', ['email', 'sms', 'whatsapp', 'push']);
            $table->string('sujet')->nullable();
            $table->text('contenu');
            $table->boolean('actif')->default(true);

            $table->timestamps();

            $table->unique(['structure_id', 'type_evenement', 'canal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
