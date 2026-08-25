<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maternity_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maternity_record_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('practitioner_id')->constrained('users')->cascadeOnDelete();

            $table->enum('mode', ['voie_basse', 'cesarienne']);
            $table->dateTime('delivered_at');
            $table->text('complications')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maternity_deliveries');
    }
};
