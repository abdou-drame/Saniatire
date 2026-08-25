<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DialysisProgramRequest extends FormRequest
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
            'frequency_per_week' => [$sometimesOnUpdate, 'integer', 'min:1', 'max:7'],
            'dry_weight_kg' => [$sometimesOnUpdate, 'numeric', 'min:0', 'max:300'],
            'vascular_access_type' => [$sometimesOnUpdate, Rule::in(['fistule', 'catheter', 'greffon'])],
            'vascular_access_status' => ['nullable', Rule::in(['fonctionnel', 'complique'])],
            'started_at' => [$sometimesOnUpdate, 'date'],
        ];
    }
}
