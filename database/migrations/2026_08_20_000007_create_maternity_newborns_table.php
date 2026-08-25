<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * hasMany under a delivery (not hasOne) to accommodate multiple births
     * without a schema change.
     */
    public function up(): void
    {
        Schema::create('maternity_newborns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maternity_delivery_id')->constrained()->cascadeOnDelete();

            $table->enum('sex', ['m', 'f']);
            $table->unsignedInteger('birth_weight_grams');
            $table->unsignedTinyInteger('apgar_1min');
            $table->unsignedTinyInteger('apgar_5min');
            $table->unsignedTinyInteger('apgar_10min')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maternity_newborns');
    }
};
