<?php

namespace App\Domain\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Formule commerciale Saliha Health (start, pro, business, premium,
 * enterprise). Sert au suivi commercial et à la facturation manuelle,
 * jamais à l'activation de modules (vendus à la carte, voir StructureModule).
 * Prix nuls = sur devis (Enterprise).
 */
class Plan extends Model
{
    protected $fillable = [
        'code',
        'name',
        'monthly_price_fcfa',
        'annual_price_fcfa',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'monthly_price_fcfa' => 'integer',
            'annual_price_fcfa' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
