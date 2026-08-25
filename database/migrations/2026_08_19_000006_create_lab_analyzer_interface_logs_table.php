<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extension point only — no automate is actually wired up yet. When a
     * lab analyzer interface (HL7/ASTM over a middleware, or a vendor
     * driver) is connected in a later step, each inbound result message or
     * outbound order message it exchanges should be logged here as raw
     * payload + direction + status, keyed to the sample/result it concerns.
     * Nothing in the app writes to this table today.
     */
    public function up(): void
    {
        Schema::create('lab_analyzer_interface_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_sample_id')->nullable()->constrained('lab_samples')->nullOnDelete();
            $table->foreignId('lab_result_id')->nullable()->constrained('lab_results')->nullOnDelete();
            $table->enum('direction', ['inbound', 'outbound']);
            $table->json('raw_payload')->nullable();
            $table->string('status')->nullable();
            $table->dateTime('received_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_analyzer_interface_logs');
    }
};
