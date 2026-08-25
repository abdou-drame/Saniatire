<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CardioRecordRequest extends FormRequest
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
            'risk_factors' => ['nullable', 'array'],
            'risk_factors.*' => ['string', 'max:100'],
            'current_treatment' => ['nullable', 'string'],
            'examined_at' => [$sometimesOnUpdate, 'date'],
        ];
    }
}
