<?php

namespace App\Domain\Structure\Models;

use App\Domain\Patient\Models\Patient;
use App\Domain\User\Models\User;
use Database\Factories\StructureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Structure extends Model
{
    /** @use HasFactory<StructureFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'code',
        'legal_name',
        'trade_name',
        'type',
        'logo_path',
        'address',
        'city',
        'country',
        'phone',
        'email',
        'opening_hours',
        'registration_number',
        'tax_number',
        'color_primary',
        'color_secondary',
        'currency',
        'locale',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(StructureModule::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('structure');
    }
}
