<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OphtalmoRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'site_id' => [$sometimesOnUpdate, 'integer', 'exists:sites,id'],
            'patient_id' => [$sometimesOnUpdate, 'integer', 'exists:patients,id'],
            'consultation_id' => ['nullable', 'integer', 'exists:consultations,id'],
            'visual_acuity_od_uncorrected' => ['nullable', 'string', 'max:20'],
            'visual_acuity_od_corrected' => ['nullable', 'string', 'max:20'],
            'visual_acuity_og_uncorrected' => ['nullable', 'string', 'max:20'],
            'visual_acuity_og_corrected' => ['nullable', 'string', 'max:20'],
            'intraocular_pressure_od' => ['nullable', 'integer', 'min:0', 'max:80'],
            'intraocular_pressure_og' => ['nullable', 'integer', 'min:0', 'max:80'],
            'refraction_od_sphere' => ['nullable', 'numeric', 'between:-30,30'],
            'refraction_od_cylinder' => ['nullable', 'numeric', 'between:-10,10'],
            'refraction_od_axis' => ['nullable', 'integer', 'min:0', 'max:180'],
            'refraction_og_sphere' => ['nullable', 'numeric', 'between:-30,30'],
            'refraction_og_cylinder' => ['nullable', 'numeric', 'between:-10,10'],
            'refraction_og_axis' => ['nullable', 'integer', 'min:0', 'max:180'],
            'fundus_exam' => ['nullable', 'string'],
            'optical_correction_prescription' => ['nullable', 'string'],
            'examined_at' => [$sometimesOnUpdate, 'date'],
        ];
    }
}
