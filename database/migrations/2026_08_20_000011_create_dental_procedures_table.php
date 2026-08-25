<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * tooth_fdi is nullable because some acts (e.g. détartrage) are
     * full-mouth, not tied to a single tooth.
     */
    public function up(): void
    {
        Schema::create('dental_procedures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dental_chart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();
            $table->foreignId('practitioner_id')->constrained('users')->cascadeOnDelete();

            $table->string('tooth_fdi', 2)->nullable();
            $table->string('act_type');
            $table->date('performed_at');
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dental_procedures');
    }
};
