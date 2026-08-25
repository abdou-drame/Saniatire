<?php

namespace App\Domain\Hospitalisation\Models;

use App\Domain\User\Models\User;
use Database\Factories\HospitalizationDailyNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One day's care record for a hospitalization. Tenant isolation is
 * inherited through hospitalization_id, no BelongsToTenant of its own —
 * same pattern as ConsultationDiagnosis under Consultation.
 */
class HospitalizationDailyNote extends Model
{
    /** @use HasFactory<HospitalizationDailyNoteFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'hospitalization_id',
        'author_id',
        'note_date',
        'care_administered',
        'medications_given',
        'procedures_performed',
        'observations',
    ];

    protected function casts(): array
    {
        return [
            'note_date' => 'date',
        ];
    }

    public function hospitalization(): BelongsTo
    {
        return $this->belongsTo(Hospitalization::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('hospitalization_daily_note');
    }
}
