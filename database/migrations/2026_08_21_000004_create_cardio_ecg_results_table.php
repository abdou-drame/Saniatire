<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structured ECG data only — no graphical tracing at this stage.
     * tracing_file_reference is a plain string pointer so a real file
     * (uploaded later by the frontend) can be attached without a schema
     * change.
     */
    public function up(): void
    {
        Schema::create('cardio_ecg_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cardio_record_id')->constrained()->cascadeOnDelete();

            $table->dateTime('performed_at');
            $table->string('rhythm');
            $table->unsignedSmallInteger('heart_rate')->nullable();
            $table->text('anomalies')->nullable();
            $table->string('tracing_file_reference')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cardio_ecg_results');
    }
};
