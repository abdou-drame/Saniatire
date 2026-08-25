<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lightweight discriminator only — the specialty's actual clinical
     * data lives in its own dedicated tables (maternity_records,
     * dental_charts, dialysis_programs, ...), each linked back via a
     * nullable consultation_id. This column exists purely so the
     * timeline/registry can know which specialty table to look up
     * without joining against every specialty table on every query.
     * See App\Domain\Shared\Specialty\SpecialtyRegistry.
     */
    public function up(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->string('specialty_type')->nullable()->after('appointment_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('consultations', function (Blueprint $table) {
            $table->dropColumn('specialty_type');
        });
    }
};
