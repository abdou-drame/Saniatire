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
            'content' => $this->content,
            'status' => $this->status,
            'validated_at' => $this->validated_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
