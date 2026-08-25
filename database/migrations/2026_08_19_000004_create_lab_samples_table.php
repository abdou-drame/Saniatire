<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A single sample (e.g. one blood draw) can cover several requested
     * analyses at once, so lab_samples is attached to the order, not to a
     * specific lab_order_item — matching real-world specimen collection.
     */
    public function up(): void
    {
        Schema::create('lab_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained()->cascadeOnDelete();
            $table->string('barcode')->unique();
            $table->string('sample_type');
            $table->dateTime('collected_at');
            $table->foreignId('collected_by')->constrained('users')->cascadeOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_samples');
    }
};
