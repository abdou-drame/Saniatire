<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * scale_name is free text on purpose — the model stays generic
     * (PHQ-9, HAD, MADRS, ...) instead of hardcoding one scale per table.
     */
    public function up(): void
    {
        Schema::create('mental_health_scale_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mental_health_record_id')->constrained()->cascadeOnDelete();

            $table->string('scale_name');
            $table->decimal('score', 6, 2);
            $table->date('scored_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mental_health_scale_scores');
    }
};
