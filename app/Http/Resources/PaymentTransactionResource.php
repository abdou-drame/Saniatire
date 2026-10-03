<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** raw_payload jamais exposé : il contient les coordonnées du payeur. */
class PaymentTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'plan_name' => $this->whenLoaded('plan', fn () => $this->plan?->name),
            'period' => $this->period,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'provider' => $this->provider,
            'payment_url' => $this->payment_url,
            'origin' => str_starts_with((string) $this->initiated_by, 'structure_admin:') ? 'structure_admin' : 'platform_admin',
            'subscription_id' => $this->subscription_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
