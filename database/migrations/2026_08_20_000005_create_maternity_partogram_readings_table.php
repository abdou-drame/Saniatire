<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One timestamped reading during labor. A time series, not a single
     * snapshot — this is exactly the kind of sub-structure a generic JSON
     * blob would make awkward to append to / query over time.
     */
    public function up(): void
    {
        Schema::create('maternity_partogram_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maternity_partogram_id')->constrained()->cascadeOnDelete();

            $table->dateTime('recorded_at');
            $table->decimal('cervical_dilation_cm', 3, 1);
            $table->unsignedSmallInteger('fetal_heart_rate')->nullable();
            $table->unsignedSmallInteger('contractions_per_10min')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maternity_partogram_readings');
    }
};
