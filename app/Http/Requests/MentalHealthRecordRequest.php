<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MentalHealthRecordRequest extends FormRequest
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
            'consultation_reason' => [$sometimesOnUpdate, 'string'],
            'clinical_evaluation' => ['nullable', 'string'],
            'ongoing_treatment' => ['nullable', 'string'],
        ];
    }
}
