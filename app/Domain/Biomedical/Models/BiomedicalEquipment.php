<?php

namespace App\Domain\Biomedical\Models;

use App\Domain\Achats\Models\Supplier;
use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Site;
use Database\Factories\BiomedicalEquipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BiomedicalEquipment extends Model
{
    /** @use HasFactory<BiomedicalEquipmentFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'structure_id',
        'site_id',
        'supplier_id',
        'nom',
        'categorie',
        'numero_serie',
        'date_acquisition',
        'date_fin_garantie',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_acquisition' => 'date',
            'date_fin_garantie' => 'date',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(EquipmentMaintenance::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('biomedical_equipment');
    }
}
