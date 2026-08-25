<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The configurable heart of the purchasing approval circuit (§2 cahier
     * des charges) : each structure defines its own chain of approval
     * levels, each triggered once a purchase order's amount reaches
     * min_amount, requiring a user holding role_name to clear it. Nothing
     * about level count or thresholds is hardcoded — it is entirely driven
     * by the rows a structure creates here.
     */
    public function up(): void
    {
        Schema::create('approval_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('level');
            $table->decimal('min_amount', 12, 2);
            $table->string('role_name');

            $table->timestamps();

            $table->unique(['structure_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_rules');
    }
};
