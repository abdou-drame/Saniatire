<?php

namespace App\Domain\SoinsDomicile\Models;

use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class HomeCareVisit extends Model
{
    use LogsActivity;

    protected $fillable = [
        'home_care_record_id',
        'intervenant_id',
        'care_type',
        'visit_datetime',
        'report',
    ];

    protected function casts(): array
    {
        return [
            'visit_datetime' => 'datetime',
        ];
    }

    public function homeCareRecord(): BelongsTo
    {
        return $this->belongsTo(HomeCareRecord::class);
    }

    public function intervenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'intervenant_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('home_care_visit');
    }
}
