<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per measurement — a plain time series, so a growth curve
     * can be plotted later (frontend) without ever recomputing history.
     */
    public function up(): void
    {
        Schema::create('pediatric_growth_measurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pediatric_record_id')->constrained()->cascadeOnDelete();

            $table->date('measured_at');
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->decimal('height_cm', 5, 2)->nullable();
            $table->decimal('head_circumference_cm', 5, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pediatric_growth_measurements');
    }
};
