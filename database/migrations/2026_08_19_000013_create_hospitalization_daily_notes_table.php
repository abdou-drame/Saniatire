<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hospitalization_daily_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hospitalization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();

            $table->date('note_date');
            $table->text('care_administered')->nullable();
            $table->text('medications_given')->nullable();
            $table->text('procedures_performed')->nullable();
            $table->text('observations')->nullable();

            $table->timestamps();

            $table->index(['hospitalization_id', 'note_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hospitalization_daily_notes');
    }
};
