<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A completed/logged passage. Planning the *next* passage reuses the
     * existing appointments module (Appointment) instead of a bespoke
     * scheduler here — see HomeCareRecordController's docblock.
     */
    public function up(): void
    {
        Schema::create('home_care_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_care_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('intervenant_id')->constrained('users')->cascadeOnDelete();

            $table->string('care_type');
            $table->dateTime('visit_datetime');
            $table->text('report')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_care_visits');
    }
};
