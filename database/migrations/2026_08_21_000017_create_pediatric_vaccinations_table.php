<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No generic vaccination module exists yet in the socle, so this is a
     * simple pediatrics-specific structure per the étape 4b brief — not a
     * shared immunization registry.
     */
    public function up(): void
    {
        Schema::create('pediatric_vaccinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pediatric_record_id')->constrained()->cascadeOnDelete();

            $table->string('vaccine_name');
            $table->unsignedTinyInteger('dose_number')->nullable();
            $table->date('administered_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pediatric_vaccinations');
    }
};
