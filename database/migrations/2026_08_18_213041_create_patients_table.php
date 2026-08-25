<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->string('patient_number')->unique();

            $table->string('first_name');
            $table->string('last_name');
            $table->enum('sex', ['M', 'F']);
            $table->date('birth_date');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('profession')->nullable();
            $table->string('nationality')->nullable();

            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relationship')->nullable();

            $table->string('photo_path')->nullable();
            $table->string('id_document_path')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Helps surface potential duplicates (same identity triplet).
            $table->index(['structure_id', 'last_name', 'first_name', 'birth_date'], 'patients_duplicate_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
