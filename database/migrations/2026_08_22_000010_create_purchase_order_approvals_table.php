<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per required approval level, snapshotted from approval_rules
     * at submission time (level/role_name/min_amount duplicated here) so a
     * later edit to a structure's rules never rewrites the history of an
     * order already in flight. approval_rule_id is kept only for traceability
     * and is nulled, not cascaded, if the source rule is later deleted.
     */
    public function up(): void
    {
        Schema::create('purchase_order_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_rule_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedInteger('level');
            $table->decimal('min_amount', 12, 2);
            $table->string('role_name');

            $table->enum('statut', ['en_attente', 'validee'])->default('en_attente');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();

            $table->timestamps();

            $table->unique(['purchase_order_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_approvals');
    }
};
