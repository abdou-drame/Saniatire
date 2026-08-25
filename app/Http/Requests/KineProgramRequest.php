<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class KineProgramRequest extends FormRequest
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
            'affected_area' => [$sometimesOnUpdate, 'string', 'max:255'],
            'initial_range_of_motion' => ['nullable', 'string', 'max:255'],
            'initial_pain_scale' => ['nullable', 'integer', 'min:0', 'max:10'],
            'objectives' => ['nullable', 'string'],
            'started_at' => [$sometimesOnUpdate, 'date'],
        ];
    }
}
