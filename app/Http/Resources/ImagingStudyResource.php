<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImagingStudyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'imaging_order_id' => $this->imaging_order_id,
            'study_instance_uid' => $this->study_instance_uid,
            'accession_number' => $this->accession_number,
            'modality' => $this->modality,
            'performed_at' => $this->performed_at,
            'performed_by' => $this->performed_by,
            'external_reference_url' => $this->external_reference_url,
            'storage_reference' => $this->storage_reference,
            'status' => $this->status,
            'report' => new ImagingReportResource($this->whenLoaded('report')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
