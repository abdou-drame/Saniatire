<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QueueEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
            'practitioner_id' => ['nullable', 'integer', 'exists:users,id'],
            'service' => ['required', 'string', 'max:255'],
            'priority' => ['sometimes', Rule::in(['normale', 'urgente', 'tres_urgente'])],
        ];
    }
}
