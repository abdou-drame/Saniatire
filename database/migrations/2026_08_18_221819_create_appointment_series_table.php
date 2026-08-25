<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Grouping record for a recurring appointment: individual occurrences
     * live in `appointments` and point back here via appointment_series_id.
     */
    public function up(): void
    {
        Schema::create('appointment_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('practitioner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->enum('recurrence_rule', ['weekly', 'biweekly', 'monthly']);
            $table->unsignedInteger('occurrences_count');
            $table->unsignedInteger('duration_minutes');
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_series');
    }
};
