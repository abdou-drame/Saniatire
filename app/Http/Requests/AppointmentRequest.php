<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentRequest extends FormRequest
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
            'resource_name' => ['nullable', 'string', 'max:255'],
            'starts_at' => [$sometimesOnUpdate, 'date'],
            'duration_minutes' => [$sometimesOnUpdate, 'integer', 'min:5', 'max:480'],
            'reason' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['planifie', 'confirme', 'en_cours', 'termine', 'annule', 'absent'])],
            // Étape 6 §3 : dérogation au planning RH théorique, réservée à
            // appointments.override_planning (voir AppointmentController).
            'force_override' => ['sometimes', 'boolean'],
        ];
    }
}
