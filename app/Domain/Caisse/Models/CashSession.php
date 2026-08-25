<?php

namespace App\Domain\Caisse\Models;

use App\Domain\Shared\Tenancy\BelongsToTenant;
use App\Domain\Structure\Models\Site;
use App\Domain\User\Models\User;
use Database\Factories\CashSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CashSession extends Model
{
    /** @use HasFactory<CashSessionFactory> */
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $fillable = [
        'structure_id',
        'site_id',
        'caissier_id',
        'montant_ouverture',
        'montant_cloture',
        'ecart',
        'ouverte_le',
        'fermee_le',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'montant_ouverture' => 'decimal:2',
            'montant_cloture' => 'decimal:2',
            'ecart' => 'decimal:2',
            'ouverte_le' => 'datetime',
            'fermee_le' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function caissier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'caissier_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->useLogName('cash_session');
    }
}
