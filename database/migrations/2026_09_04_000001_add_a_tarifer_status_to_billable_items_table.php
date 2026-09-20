<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the 'a_tarifer' status: BillingService::recordService() used to
     * silently skip creating a BillableItem when no active ServiceTariff
     * matched the act's code, leaving completed clinical acts with zero
     * billing trace. It now always creates a line, using this status
     * (prix_unitaire/montant_total = 0) when pricing is missing, so the
     * gap is visible in Facturation instead of silent.
     *
     * The enum is widened to a plain string column (allowed values are
     * enforced application-side, e.g. BillableItemController validation)
     * rather than a driver-specific CHECK constraint, so this migration
     * behaves the same on pgsql (production) and sqlite (tests).
     *
     * The unique index on (billable_type, billable_id) prevents the same
     * clinical act from ever producing two billing lines — needed because
     * recordService() has no other guard against being invoked twice for
     * the same record (e.g. during a retroactive backfill).
     */
    public function up(): void
    {
        Schema::table('billable_items', function (Blueprint $table) {
            $table->string('statut')->default('a_facturer')->change();
        });

        // Changing an enum column's type on pgsql leaves its original CHECK
        // constraint (still restricted to the 3 old values) in place — drop
        // it explicitly so 'a_tarifer' is actually accepted.
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE billable_items DROP CONSTRAINT IF EXISTS billable_items_statut_check');
        }

        Schema::table('billable_items', function (Blueprint $table) {
            $table->unique(['billable_type', 'billable_id']);
        });
    }

    public function down(): void
    {
        Schema::table('billable_items', function (Blueprint $table) {
            $table->dropUnique(['billable_type', 'billable_id']);
        });

        Schema::table('billable_items', function (Blueprint $table) {
            $table->enum('statut', ['a_facturer', 'facturee', 'annulee'])->default('a_facturer')->change();
        });
    }
};
