<?php

namespace App\Domain\Platform\Models;

use App\Domain\Structure\Models\Structure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une session de paiement d'abonnement auprès d'un prestataire (DexPay
 * aujourd'hui, `provider` prévu pour en ajouter un autre). Même raison que
 * Subscription pour l'absence de BelongsToTenant : donnée de facturation
 * sur une structure, lue par la plateforme, filtrée explicitement par
 * structure_id côté structure.
 */
class PaymentTransaction extends Model
{
    public const STATUS_PENDING = 'en_attente';

    public const STATUS_COMPLETED = 'complete';

    public const STATUS_FAILED = 'echoue';

    public const STATUS_CANCELLED = 'annule';

    public const PERIODS = ['monthly', 'annual'];

    protected $fillable = [
        'structure_id',
        'plan_id',
        'period',
        'reference',
        'checkout_session_id',
        'transaction_id',
        'amount',
        'currency',
        'status',
        'provider',
        'payment_url',
        'raw_payload',
        'initiated_by',
        'subscription_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'raw_payload' => 'array',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class)->withTrashed();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
