<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * resolved_at/closed_at existaient déjà mais sans tracer qui a résolu ou
     * clôturé la réclamation (ComplaintController::resolve()/close()) —
     * lacune de capture, distincte de gestionnaire_id (qui en est le
     * gestionnaire assigné tout au long du workflow).
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->after('closed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropConstrainedForeignId('closed_by');
        });
    }
};
