<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('practitioner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('appointment_series_id')->nullable()->constrained('appointment_series')->nullOnDelete();
            $table->string('resource_name')->nullable();

            $table->dateTime('starts_at');
            $table->unsignedInteger('duration_minutes');
            $table->string('reason')->nullable();
            $table->enum('status', ['planifie', 'confirme', 'en_cours', 'termine', 'annule', 'absent'])
                ->default('planifie');
            $table->boolean('is_recurring')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Availability lookups: "is this practitioner/resource busy at this time?".
            $table->index(['structure_id', 'practitioner_id', 'starts_at'], 'appointments_practitioner_lookup_index');
            $table->index(['structure_id', 'site_id', 'starts_at'], 'appointments_site_calendar_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
