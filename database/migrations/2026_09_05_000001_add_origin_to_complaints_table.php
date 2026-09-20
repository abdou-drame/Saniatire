<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * origin distingue une réclamation saisie par le personnel (staff, valeur
     * historique par défaut — toutes les réclamations existantes le sont)
     * d'une réclamation soumise directement par le patient depuis son portail
     * (patient). Purement informatif côté écran personnel ; ne change rien au
     * workflow ni aux permissions (ComplaintController::assertCanManage reste
     * inchangé).
     */
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->enum('origin', ['staff', 'patient'])->default('staff')->after('service_concerne');
        });
    }

    public function down(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
