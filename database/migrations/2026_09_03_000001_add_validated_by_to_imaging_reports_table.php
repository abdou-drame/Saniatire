<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * validated_at existait déjà (ImagingReportController::validateReport())
     * mais sans tracer qui a validé — validated_by comble cette lacune de
     * capture, distincte de author_id (qui a rédigé le compte rendu).
     */
    public function up(): void
    {
        Schema::table('imaging_reports', function (Blueprint $table) {
            $table->foreignId('validated_by')->nullable()->after('validated_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('imaging_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('validated_by');
        });
    }
};
