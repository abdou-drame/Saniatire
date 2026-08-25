<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignId('practitioner_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('service');
            $table->enum('priority', ['normale', 'urgente', 'tres_urgente'])->default('normale');
            $table->enum('status', ['en_attente', 'appele', 'en_consultation', 'sorti'])->default('en_attente');

            // One timestamp per status transition, so real wait time is
            // computable after the fact rather than only "now - arrived_at".
            $table->dateTime('arrived_at');
            $table->dateTime('called_at')->nullable();
            $table->dateTime('in_consultation_at')->nullable();
            $table->dateTime('exited_at')->nullable();

            $table->timestamps();

            $table->index(['structure_id', 'site_id', 'status'], 'queue_entries_live_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_entries');
    }
};
