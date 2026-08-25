<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maternity_partograms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maternity_record_id')->unique()->constrained()->cascadeOnDelete();

            $table->dateTime('labor_started_at');
            $table->enum('status', ['en_cours', 'termine'])->default('en_cours');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maternity_partograms');
    }
};
