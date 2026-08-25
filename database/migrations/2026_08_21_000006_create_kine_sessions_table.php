<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kine_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kine_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('practitioner_id')->constrained('users')->cascadeOnDelete();

            $table->dateTime('session_date');
            $table->text('exercises_performed')->nullable();
            $table->text('evolution')->nullable();
            $table->unsignedTinyInteger('pain_scale')->nullable();
            $table->text('observations')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kine_sessions');
    }
};
