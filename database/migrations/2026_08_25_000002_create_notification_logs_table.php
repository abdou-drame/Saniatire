<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nommée "notification_logs" et non "notifications" : User utilise déjà
     * le trait Illuminate\Notifications\Notifiable, qui suppose une table
     * "notifications" au schéma imposé par Laravel (id uuid, type, data,
     * read_at...). Notre historique d'envoi maison a un schéma différent
     * (type_evenement, canal, statut...) — un nom distinct évite toute
     * collision si le trait natif est utilisé un jour ailleurs.
     */
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();

            // Destinataire : User ou Patient — relation polymorphe maison.
            $table->morphs('notifiable');

            $table->string('type_evenement');
            $table->enum('canal', ['email', 'sms', 'whatsapp', 'push']);
            $table->string('sujet_final')->nullable();
            $table->text('contenu_final');
            $table->string('destinataire');

            $table->enum('statut', ['en_attente', 'envoyee', 'echouee', 'lue'])->default('en_attente');
            // null = envoi immédiat ; une date future = rappel programmé,
            // consommé par la commande notifications:process-due.
            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('envoye_at')->nullable();
            $table->text('erreur')->nullable();

            $table->timestamps();

            $table->index(['statut', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
