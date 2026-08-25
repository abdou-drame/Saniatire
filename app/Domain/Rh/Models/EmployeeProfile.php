<?php

namespace App\Domain\Rh\Models;

use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\User\Models\User;
use Database\Factories\EmployeeProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EmployeeProfile extends Model
{
    /** @use HasFactory<EmployeeProfileFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'structure_id',
        'user_id',
        'date_embauche',
        'type_contrat',
        'statut_emploi',
        'qualification',
        'numero_ordre',
    ];

    protected function casts(): array
    {
        return [
            'date_embauche' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('employee_profile');
    }
}
