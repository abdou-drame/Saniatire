<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * estimated_delivery_date is deliberately absent from these rules — it is
 * always computed server-side from last_menstrual_period_date, never
 * accepted from the payload (see MaternityRecord's saving hook).
 */
class MaternityRecordRequest extends FormRequest
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
            'last_menstrual_period_date' => [$sometimesOnUpdate, 'date'],
        ];
    }
}
