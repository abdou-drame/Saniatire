<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConsultationRequest extends FormRequest
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
            'practitioner_id' => [$sometimesOnUpdate, 'integer', 'exists:users,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'reason' => [$sometimesOnUpdate, 'string', 'max:255'],
            'history_of_illness' => ['nullable', 'string'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'temperature_c' => ['nullable', 'numeric', 'min:25', 'max:45'],
            'blood_pressure_systolic' => ['nullable', 'integer', 'min:0', 'max:300'],
            'blood_pressure_diastolic' => ['nullable', 'integer', 'min:0', 'max:200'],
            'heart_rate' => ['nullable', 'integer', 'min:0', 'max:300'],
            'respiratory_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'spo2' => ['nullable', 'integer', 'min:0', 'max:100'],
            'glycemia' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pain_scale' => ['nullable', 'integer', 'min:0', 'max:10'],
            'extra_vitals' => ['nullable', 'array'],
            'clinical_exam' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
            'referral' => ['nullable', 'string', 'max:255'],
            'follow_up_suggested_at' => ['nullable', 'date'],
        ];
    }
}
