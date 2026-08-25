<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OphtalmoRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'structure_id' => $this->structure_id,
            'site_id' => $this->site_id,
            'patient_id' => $this->patient_id,
            'consultation_id' => $this->consultation_id,
            'visual_acuity_od_uncorrected' => $this->visual_acuity_od_uncorrected,
            'visual_acuity_od_corrected' => $this->visual_acuity_od_corrected,
            'visual_acuity_og_uncorrected' => $this->visual_acuity_og_uncorrected,
            'visual_acuity_og_corrected' => $this->visual_acuity_og_corrected,
            'intraocular_pressure_od' => $this->intraocular_pressure_od,
            'intraocular_pressure_og' => $this->intraocular_pressure_og,
            'refraction_od_sphere' => $this->refraction_od_sphere,
            'refraction_od_cylinder' => $this->refraction_od_cylinder,
            'refraction_od_axis' => $this->refraction_od_axis,
            'refraction_og_sphere' => $this->refraction_og_sphere,
            'refraction_og_cylinder' => $this->refraction_og_cylinder,
            'refraction_og_axis' => $this->refraction_og_axis,
            'fundus_exam' => $this->fundus_exam,
            'optical_correction_prescription' => $this->optical_correction_prescription,
            'examined_at' => $this->examined_at?->format('Y-m-d'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
