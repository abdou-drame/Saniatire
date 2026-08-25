<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LabOrderRequest extends FormRequest
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
            'prescriber_id' => [$sometimesOnUpdate, 'integer', 'exists:users,id'],
            'consultation_id' => ['nullable', 'integer', 'exists:consultations,id'],
            'notes' => ['nullable', 'string'],
            'items' => [$sometimesOnUpdate, 'array', 'min:1'],
            'items.*.loinc_code_id' => ['required_with:items', 'integer', 'exists:loinc_codes,id'],
        ];
    }
}
