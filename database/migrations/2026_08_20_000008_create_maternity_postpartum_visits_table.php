<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maternity_postpartum_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maternity_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('practitioner_id')->constrained('users')->cascadeOnDelete();

            $table->date('visit_date');
            $table->unsignedSmallInteger('blood_pressure_systolic')->nullable();
            $table->unsignedSmallInteger('blood_pressure_diastolic')->nullable();
            $table->decimal('temperature_c', 3, 1)->nullable();
            $table->enum('bleeding_status', ['normal', 'anormal'])->default('normal');
            $table->string('breastfeeding_status')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maternity_postpartum_visits');
    }
};
