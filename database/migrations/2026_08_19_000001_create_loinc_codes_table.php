<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shared reference table (LOINC), NOT tenant-scoped — every structure
     * prescribes and reports lab analyses against the same referentiel,
     * exactly like icd_codes (see database/seeders/README-LOINC.md for how
     * to import the full official dataset later without touching this
     * schema).
     */
    public function up(): void
    {
        Schema::create('loinc_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('label');
            $table->string('component')->nullable();
            $table->string('default_unit')->nullable();
            $table->string('version')->default('LOINC');
            $table->enum('status', ['actif', 'deprecie'])->default('actif');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loinc_codes');
    }
};
