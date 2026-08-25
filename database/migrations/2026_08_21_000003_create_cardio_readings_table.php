<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Several blood-pressure/heart-rate measurements possible per record —
     * a time series, hence its own table rather than columns on
     * cardio_records.
     */
    public function up(): void
    {
        Schema::create('cardio_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cardio_record_id')->constrained()->cascadeOnDelete();

            $table->dateTime('measured_at');
            $table->unsignedSmallInteger('blood_pressure_systolic');
            $table->unsignedSmallInteger('blood_pressure_diastolic');
            $table->unsignedSmallInteger('heart_rate');
            $table->enum('rhythm', ['regulier', 'irregulier'])->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cardio_readings');
    }
};
