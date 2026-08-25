<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * billing_status mirrors lab_orders — a plain placeholder for the
     * future billing module (étape 5), no real billing logic here.
     */
    public function up(): void
    {
        Schema::create('imaging_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescriber_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();

            $table->enum('exam_type', ['radio', 'echo', 'scanner', 'irm']);
            $table->enum('status', [
                'demande', 'planifie', 'realise', 'en_interpretation', 'cr_redige', 'valide', 'transmis', 'annule',
            ])->default('demande');
            $table->enum('billing_status', ['pending', 'done'])->default('pending');

            $table->dateTime('ordered_at');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['structure_id', 'patient_id', 'status'], 'imaging_orders_patient_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_orders');
    }
};
