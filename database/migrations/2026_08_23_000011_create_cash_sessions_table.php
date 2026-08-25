<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('caissier_id')->constrained('users')->cascadeOnDelete();

            $table->decimal('montant_ouverture', 12, 2)->default(0);
            $table->decimal('montant_cloture', 12, 2)->nullable();
            $table->decimal('ecart', 12, 2)->nullable();
            $table->dateTime('ouverte_le');
            $table->dateTime('fermee_le')->nullable();
            $table->enum('statut', ['ouverte', 'fermee'])->default('ouverte');

            $table->timestamps();

            $table->index(['caissier_id', 'site_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
