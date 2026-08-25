<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shared reference table (WHO ICD-10 / ICD-11), NOT tenant-scoped —
     * every structure searches and codes against the same referentiel.
     * See database/seeders/README-ICD.md for how to import the full
     * official dataset later without touching this schema.
     */
    public function up(): void
    {
        Schema::create('icd_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->enum('version', ['CIM-10', 'CIM-11']);
            $table->string('label');
            $table->foreignId('parent_id')->nullable()->constrained('icd_codes')->nullOnDelete();
            $table->enum('level', ['chapitre', 'groupe', 'code'])->default('code');
            $table->enum('status', ['actif', 'deprecie'])->default('actif');
            $table->timestamps();

            $table->unique(['code', 'version']);
            $table->index(['version', 'parent_id'], 'icd_codes_hierarchy_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icd_codes');
    }
};
