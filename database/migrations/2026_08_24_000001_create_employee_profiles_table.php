<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->date('date_embauche')->nullable();
            $table->string('type_contrat')->nullable();
            $table->enum('statut_emploi', ['actif', 'en_conge', 'suspendu', 'termine'])->default('actif');
            $table->string('qualification')->nullable();
            $table->string('numero_ordre')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_profiles');
    }
};
