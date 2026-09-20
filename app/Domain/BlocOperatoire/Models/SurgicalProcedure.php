<?php

namespace App\Domain\BlocOperatoire\Models;

use App\Domain\Hospitalisation\Models\Hospitalization;
use App\Domain\Patient\Models\Patient;
use App\Domain\Shared\Billing\Billable;
use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Site;
use App\Domain\User\Models\User;
use Database\Factories\SurgicalProcedureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * status is intentionally absent from SurgicalProcedureRequest's rules, so
 * it can never be set through the generic store/update payload — it only
 * ever moves via SurgicalProcedureController::start/complete/cancel, and
 * complete() is the one place the checklist-completion rule is enforced.
 */
class SurgicalProcedure extends Model implements Billable
{
    /** @use HasFactory<SurgicalProcedureFactory> */
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    private const REQUIRED_CHECKLIST_STEPS = ['avant_anesthesie', 'avant_incision', 'avant_sortie_bloc'];

    protected $fillable = [
        'structure_id',
        'site_id',
        'patient_id',
        'hospitalization_id',
        'surgeon_id',
        'anesthesiologist_id',
        'operating_room',
        'procedure_type',
        'scheduled_at',
        'performed_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'performed_at' => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function hospitalization(): BelongsTo
    {
        return $this->belongsTo(Hospitalization::class);
    }

    public function surgeon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surgeon_id');
    }

    public function anesthesiologist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anesthesiologist_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(SurgicalChecklist::class);
    }

    /**
     * The blocking rule (§4/§7 cahier des charges): all 3 checklist steps
     * must be validated before the procedure can be marked "terminée".
     */
    public function hasCompleteChecklist(): bool
    {
        $validatedSteps = $this->checklists()->whereNotNull('validated_at')->pluck('step')->all();

        return count(array_intersect(self::REQUIRED_CHECKLIST_STEPS, $validatedSteps)) === count(self::REQUIRED_CHECKLIST_STEPS);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('surgical_procedure');
    }

    public function billingPatientId(): int
    {
        return $this->patient_id;
    }

    public function billingStructureId(): int
    {
        return $this->structure_id;
    }

    public function billingCategorie(): string
    {
        return 'chirurgie';
    }

    public function billingLibelle(): string
    {
        return "Intervention chirurgicale : {$this->procedure_type}";
    }

    public function billingTariffCode(): string
    {
        return 'CHIRURGIE_INTERVENTION';
    }

    public function billingQuantite(): int
    {
        return 1;
    }
}
