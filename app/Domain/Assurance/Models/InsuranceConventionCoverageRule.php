<?php

namespace App\Domain\Assurance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InsuranceConventionCoverageRule extends Model
{
    use LogsActivity;

    protected $fillable = [
        'insurance_convention_id',
        'categorie',
        'taux_couverture',
        'plafond_montant',
        'exclu',
    ];

    protected function casts(): array
    {
        return [
            'taux_couverture' => 'decimal:2',
            'plafond_montant' => 'decimal:2',
            'exclu' => 'boolean',
        ];
    }

    public function convention(): BelongsTo
    {
        return $this->belongsTo(InsuranceConvention::class, 'insurance_convention_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('insurance_convention_coverage_rule');
    }
}
