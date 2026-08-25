<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImagingOrderRequest extends FormRequest
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
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'exam_type' => [$sometimesOnUpdate, Rule::in(['radio', 'echo', 'scanner', 'irm'])],
            'notes' => ['nullable', 'string'],
        ];
    }
}
