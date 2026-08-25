<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imaging_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_study_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();

            $table->text('content');
            $table->enum('status', ['brouillon', 'valide'])->default('brouillon');
            $table->dateTime('validated_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_reports');
    }
};
