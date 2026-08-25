<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dental_treatment_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dental_treatment_plan_id')->constrained()->cascadeOnDelete();

            $table->string('tooth_fdi', 2)->nullable();
            $table->string('act_type');
            $table->enum('status', ['prevu', 'realise', 'annule'])->default('prevu');
            $table->date('planned_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_treatment_plan_items');
    }
};
