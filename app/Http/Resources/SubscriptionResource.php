<?php

namespace App\Http\Resources;

use App\Domain\Platform\SubscriptionState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'grace_ends_at' => SubscriptionState::graceEndsAt($this->resource)->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'created_by_name' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'created_at' => $this->created_at,
        ];
    }
}
