<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajoutée pour l'étape 7a (rappel d'échéance de facture) : sans date
     * d'échéance, aucune notification de rappel ne peut être calculée.
     * Nullable pour ne rien casser sur les factures déjà émises.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->date('date_echeance')->nullable()->after('date_emission');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('date_echeance');
        });
    }
};
