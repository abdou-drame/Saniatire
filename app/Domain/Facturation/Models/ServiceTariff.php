<?php

namespace App\Domain\Facturation\Models;

use App\Domain\Shared\Tenancy\BelongsToTenant;
use Database\Factories\ServiceTariffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ServiceTariff extends Model
{
    /** @use HasFactory<ServiceTariffFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'structure_id',
        'code',
        'libelle',
        'categorie',
        'prix_unitaire',
        'actif',
    ];

    protected function casts(): array
    {
        return [
            'prix_unitaire' => 'decimal:2',
            'actif' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('service_tariff');
    }
}
