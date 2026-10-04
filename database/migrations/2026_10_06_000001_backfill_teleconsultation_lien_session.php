<?php

use App\Domain\Teleconsultation\Models\Teleconsultation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Les téléconsultations encore ouvertes, créées avant la génération
     * automatique des salles Jitsi, reçoivent leur lien. Les clôturées ou
     * annulées restent sans lien.
     */
    public function up(): void
    {
        DB::table('teleconsultations')
            ->whereNull('lien_session')
            ->whereIn('statut', ['planifiee', 'en_cours'])
            ->orderBy('id')
            ->pluck('id')
            ->each(fn (int $id) => DB::table('teleconsultations')
                ->where('id', $id)
                ->update(['lien_session' => Teleconsultation::genererLienSession()]));
    }

    public function down(): void
    {
        // Rien à défaire : un lien de salle n'a pas de valeur antérieure.
    }
};
