<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pediatric_development_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pediatric_record_id')->constrained()->cascadeOnDelete();

            $table->unsignedSmallInteger('age_months');
            $table->text('observation');
            $table->date('observed_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pediatric_development_observations');
    }
};
