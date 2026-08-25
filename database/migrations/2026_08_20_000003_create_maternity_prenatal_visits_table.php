<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One CPN (consultation prénatale) visit. Tenant isolation is
     * inherited through maternity_record_id — same pattern as
     * lab_order_items under lab_orders — no structure_id of its own.
     */
    public function up(): void
    {
        Schema::create('maternity_prenatal_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maternity_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('practitioner_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedSmallInteger('visit_number');
            $table->unsignedSmallInteger('gestational_age_weeks');
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->unsignedSmallInteger('blood_pressure_systolic')->nullable();
            $table->unsignedSmallInteger('blood_pressure_diastolic')->nullable();
            $table->decimal('fundal_height_cm', 4, 1)->nullable();
            $table->string('fetal_movements')->nullable();
            $table->unsignedSmallInteger('fetal_heart_rate')->nullable();
            $table->date('visit_date');
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maternity_prenatal_visits');
    }
};
