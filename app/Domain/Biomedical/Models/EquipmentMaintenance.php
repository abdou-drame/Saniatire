<?php

namespace App\Domain\Biomedical\Models;

use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\User\Models\User;
use Database\Factories\EquipmentMaintenanceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EquipmentMaintenance extends Model
{
    /** @use HasFactory<EquipmentMaintenanceFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $table = 'equipment_maintenance';

    protected $fillable = [
        'structure_id',
        'biomedical_equipment_id',
        'intervenant_user_id',
        'type',
        'date_prevue',
        'date_realisee',
        'intervenant_externe',
        'cout',
        'description',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date_prevue' => 'date',
            'date_realisee' => 'date',
        ];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(BiomedicalEquipment::class, 'biomedical_equipment_id');
    }

    public function intervenant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'intervenant_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('equipment_maintenance');
    }
}
