<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Constants taken at several points during a single session — a time
     * series, hence its own table rather than columns on dialysis_sessions.
     */
    public function up(): void
    {
        Schema::create('dialysis_session_vitals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dialysis_session_id')->constrained()->cascadeOnDelete();

            $table->dateTime('measured_at');
            $table->unsignedSmallInteger('blood_pressure_systolic');
            $table->unsignedSmallInteger('blood_pressure_diastolic');
            $table->unsignedSmallInteger('heart_rate');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dialysis_session_vitals');
    }
};
