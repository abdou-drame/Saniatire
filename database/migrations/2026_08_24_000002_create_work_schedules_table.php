<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();

            // Exactly one of jour_semaine (recurring weekly template) /
            // date (one-off entry: worked public holiday, replacement
            // shift) is set — enforced in WorkScheduleRequest rather than
            // a DB-level CHECK, since SQLite (test suite) does not enforce
            // those reliably.
            $table->unsignedTinyInteger('jour_semaine')->nullable();
            $table->date('date')->nullable();
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->enum('type', ['normal', 'garde', 'astreinte'])->default('normal');

            $table->timestamps();

            $table->index(['user_id', 'jour_semaine']);
            $table->index(['user_id', 'date']);
            $table->index(['site_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
