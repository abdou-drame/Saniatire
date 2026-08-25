<?php

namespace App\Domain\Imagerie\Models;

use App\Domain\User\Models\User;
use Database\Factories\ImagingReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * The radiologist's report for one imaging_study. Tenant isolation is
 * inherited through imaging_study_id, no BelongsToTenant of its own.
 */
class ImagingReport extends Model
{
    /** @use HasFactory<ImagingReportFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'imaging_study_id',
        'author_id',
        'content',
        'status',
        'validated_at',
    ];

    protected function casts(): array
    {
        return [
            'validated_at' => 'datetime',
        ];
    }

    public function study(): BelongsTo
    {
        return $this->belongsTo(ImagingStudy::class, 'imaging_study_id');
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
            ->useLogName('imaging_report');
    }
}
