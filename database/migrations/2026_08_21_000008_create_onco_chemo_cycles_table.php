<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * medications is a simple free-text/JSON reference to what was
     * administered — not real pharmacy stock/dispensing, out of scope here.
     */
    public function up(): void
    {
        Schema::create('onco_chemo_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onco_record_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('cycle_number');
            $table->date('cycle_date');
            $table->text('medications')->nullable();
            $table->text('side_effects')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onco_chemo_cycles');
    }
};
