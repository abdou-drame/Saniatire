<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pma_stimulation_protocols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pma_record_id')->constrained()->cascadeOnDelete();

            $table->string('protocol_type');
            $table->text('medications')->nullable();
            $table->date('started_at');
            $table->date('ended_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pma_stimulation_protocols');
    }
};
