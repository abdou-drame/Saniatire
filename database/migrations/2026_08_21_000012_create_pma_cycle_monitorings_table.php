<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pma_cycle_monitorings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pma_record_id')->constrained()->cascadeOnDelete();

            $table->date('monitoring_date');
            $table->text('echo_observations')->nullable();
            $table->decimal('hormone_level', 8, 2)->nullable();
            $table->date('puncture_date')->nullable();
            $table->date('transfer_date')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pma_cycle_monitorings');
    }
};
