<?php

namespace App\Domain\Referral\Models;

use App\Domain\Patient\Models\Patient;
use App\Domain\Referral\Scopes\ReferralVisibilityScope;
use App\Domain\Shared\Tenancy\TenantScope;
use App\Domain\Structure\Models\Site;
use App\Domain\Structure\Models\Structure;
use App\Domain\User\Models\User;
use Database\Factories\PatientReferralFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Étape 7b §5 : n'utilise PAS BelongsToTenant — un référencement appartient
 * à deux structures à la fois (origine et destination), ce que TenantScope
 * (un seul structure_id) ne peut pas exprimer. ReferralVisibilityScope est
 * le scope dédié qui joue ce rôle ici, borné à ce seul modèle.
 */
class PatientReferral extends Model
{
    /** @use HasFactory<PatientReferralFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'structure_origine_id',
        'site_origine_id',
        'structure_destination_id',
        'patient_id',
        'praticien_referent_id',
        'motif',
        'statut',
        'compte_rendu_retour',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ReferralVisibilityScope);
    }

    public function structureOrigine(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'structure_origine_id');
    }

    public function structureDestination(): BelongsTo
    {
        return $this->belongsTo(Structure::class, 'structure_destination_id');
    }

    public function siteOrigine(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_origine_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * User (personnel) applique TenantScope : sans le bypass explicite
     * ci-dessous, résoudre cette relation depuis la structure destination
     * (praticien_referent_id appartient toujours à la structure origine)
     * retournerait silencieusement null, empêchant la notification
     * referencement_accepte/refuse d'atteindre le praticien référent —
     * même patron de bypass borné que Patient::withoutGlobalScope(...)
     * dans PatientReferralController::patientResume().
     */
    public function praticienReferent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'praticien_referent_id')->withoutGlobalScope(TenantScope::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('patient_referral');
    }
}
