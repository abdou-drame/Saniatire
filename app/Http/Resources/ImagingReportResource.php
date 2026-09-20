<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImagingReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'imaging_study_id' => $this->imaging_study_id,
            'author_id' => $this->author_id,
            'author_label' => $this->whenLoaded('author', fn () => $this->author ? trim("{$this->author->first_name} {$this->author->last_name}") : null),
            'author_role' => $this->whenLoaded('author', fn () => $this->author?->getRoleNames()->first()),
            'content' => $this->content,
            'status' => $this->status,
            'validated_at' => $this->validated_at,
            'validated_by' => $this->validated_by,
            'validator_label' => $this->whenLoaded('validator', fn () => $this->validator ? trim("{$this->validator->first_name} {$this->validator->last_name}") : null),
            'validator_role' => $this->whenLoaded('validator', fn () => $this->validator?->getRoleNames()->first()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
