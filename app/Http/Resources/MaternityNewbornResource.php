<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaternityNewbornResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maternity_delivery_id' => $this->maternity_delivery_id,
            'sex' => $this->sex,
            'birth_weight_grams' => $this->birth_weight_grams,
            'apgar_1min' => $this->apgar_1min,
            'apgar_5min' => $this->apgar_5min,
            'apgar_10min' => $this->apgar_10min,
        ];
    }
}
