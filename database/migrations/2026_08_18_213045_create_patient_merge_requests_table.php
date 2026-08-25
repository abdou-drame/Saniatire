<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Traces duplicate-resolution requests. The actual merge logic
     * (reassigning clinical records, etc.) lands in a future step —
     * this only records intent and outcome.
     */
    public function up(): void
    {
        Schema::create('patient_merge_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('target_patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['pending', 'approved', 'rejected', 'merged'])->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_merge_requests');
    }
};
