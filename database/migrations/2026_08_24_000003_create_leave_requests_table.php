<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['conge_annuel', 'maladie', 'autre']);
            $table->date('date_debut');
            $table->date('date_fin');
            $table->enum('statut', ['demande', 'valide', 'refuse'])->default('demande');
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('commentaire', 1000)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'statut']);
            $table->index(['structure_id', 'date_debut', 'date_fin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
