<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * billing_status is a plain placeholder ("pending"/"done") for the
     * future billing module (étape 5) to hook into later — no real billing
     * logic lives here.
     */
    public function up(): void
    {
        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescriber_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();

            $table->enum('status', [
                'demande', 'prelevement_effectue', 'en_analyse', 'resultats_disponibles', 'transmis', 'annule',
            ])->default('demande');
            $table->enum('billing_status', ['pending', 'done'])->default('pending');

            $table->dateTime('ordered_at');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['structure_id', 'patient_id', 'status'], 'lab_orders_patient_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_orders');
    }
};
