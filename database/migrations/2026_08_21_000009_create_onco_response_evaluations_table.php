<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onco_response_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onco_record_id')->constrained()->cascadeOnDelete();

            $table->date('evaluated_at');
            $table->enum('response', ['reponse_complete', 'reponse_partielle', 'stable', 'progression']);
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onco_response_evaluations');
    }
};
