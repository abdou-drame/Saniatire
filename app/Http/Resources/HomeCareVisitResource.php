<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HomeCareVisitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'home_care_record_id' => $this->home_care_record_id,
            'intervenant_id' => $this->intervenant_id,
            'care_type' => $this->care_type,
            'visit_datetime' => $this->visit_datetime,
            'report' => $this->report,
        ];
    }
}
