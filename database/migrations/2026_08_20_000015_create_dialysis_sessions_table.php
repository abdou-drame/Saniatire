<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dialysis_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dialysis_program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('practitioner_id')->constrained('users')->cascadeOnDelete();

            $table->dateTime('session_date');
            $table->decimal('pre_weight_kg', 5, 2);
            $table->decimal('post_weight_kg', 5, 2)->nullable();
            $table->decimal('dry_weight_kg', 5, 2)->nullable();
            $table->unsignedSmallInteger('duration_minutes')->nullable();
            $table->unsignedSmallInteger('blood_flow_rate_ml_min')->nullable();
            $table->unsignedInteger('ultrafiltration_volume_ml')->nullable();
            $table->text('complications')->nullable();
            $table->enum('status', ['planifiee', 'en_cours', 'terminee'])->default('planifiee');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dialysis_sessions');
    }
};
