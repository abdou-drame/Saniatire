<?php

namespace App\Domain\Imagerie\Models;

use App\Domain\User\Models\User;
use Database\Factories\ImagingStudyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * One performed exam on an imaging_order. Tenant isolation is inherited
 * through imaging_order_id, same pattern as LabOrderItem under LabOrder —
 * no BelongsToTenant of its own.
 *
 * external_reference_url/storage_reference are the only pointers to the
 * actual images — no upload or DICOM storage happens in this app. See
 * database/seeders/README-PACS.md for the intended PACS integration.
 */
class ImagingStudy extends Model
{
    /** @use HasFactory<ImagingStudyFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'imaging_order_id',
        'study_instance_uid',
        'accession_number',
        'modality',
        'performed_at',
        'performed_by',
        'external_reference_url',
        'storage_reference',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
        ];
    }

    public function imagingOrder(): BelongsTo
    {
        return $this->belongsTo(ImagingOrder::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function report(): HasOne
    {
        return $this->hasOne(ImagingReport::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('imaging_study');
    }
}
