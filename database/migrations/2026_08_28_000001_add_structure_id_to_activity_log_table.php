<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            // Nullable : les entrées créées hors contexte de requête (ex.
            // seeders, commandes artisan) n'ont pas de structure courante.
            $table->foreignId('structure_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index('structure_id');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropConstrainedForeignId('structure_id');
        });
    }
};
