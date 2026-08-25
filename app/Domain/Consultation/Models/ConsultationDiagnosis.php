<?php

namespace App\Domain\Consultation\Models;

use App\Domain\Icd\Models\IcdCode;
use Database\Factories\ConsultationDiagnosisFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * code_snapshot/label_snapshot/version_snapshot freeze exactly what was
 * coded at the time, independent of icd_code_id — see the migration
 * docblock. Always read the *_snapshot columns for display; icd_code_id is
 * for hierarchy navigation only.
 */
class ConsultationDiagnosis extends Model
{
    /** @use HasFactory<ConsultationDiagnosisFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'consultation_id',
        'icd_code_id',
        'code_snapshot',
        'label_snapshot',
        'version_snapshot',
        'type',
        'status',
    ];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function icdCode(): BelongsTo
    {
        return $this->belongsTo(IcdCode::class);
    }

    public static function fromIcdCode(IcdCode $icdCode, string $type, string $status = 'provisoire'): array
    {
        return [
            'icd_code_id' => $icdCode->id,
            'code_snapshot' => $icdCode->code,
            'label_snapshot' => $icdCode->label,
            'version_snapshot' => $icdCode->version,
            'type' => $type,
            'status' => $status,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('consultation_diagnosis');
    }
}
