<?php

namespace App\Domain\Assurance\Models;

use App\Domain\Shared\Tenancy\BelongsToTenant;
use Database\Factories\InsuranceProviderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class InsuranceProvider extends Model
{
    /** @use HasFactory<InsuranceProviderFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'structure_id',
        'nom',
        'type',
        'contact',
    ];

    public function conventions(): HasMany
    {
        return $this->hasMany(InsuranceConvention::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('insurance_provider');
    }
}
