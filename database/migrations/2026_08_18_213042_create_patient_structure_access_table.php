<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Controlled sharing of a patient record across sibling structures
     * (e.g. within the same health group). The patient still "belongs to"
     * exactly one owning structure via patients.structure_id; a row here
     * grants read access to another structure without moving ownership.
     */
    public function up(): void
    {
        Schema::create('patient_structure_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['patient_id', 'structure_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_structure_access');
    }
};
