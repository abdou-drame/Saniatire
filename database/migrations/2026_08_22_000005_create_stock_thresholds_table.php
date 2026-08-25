<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('seuil_minimum');

            $table->timestamps();

            $table->unique(['product_id', 'site_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_thresholds');
    }
};
