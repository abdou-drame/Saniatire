<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();

            $table->string('code');
            $table->string('libelle');
            $table->string('categorie');
            $table->decimal('prix_unitaire', 10, 2);
            $table->boolean('actif')->default(true);

            $table->timestamps();

            $table->unique(['structure_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_tariffs');
    }
};
