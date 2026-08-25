<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HospitalizationRequest extends FormRequest
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
            'bed_id' => ['required', 'integer', 'exists:beds,id'],
            'attending_physician_id' => ['required', 'integer', 'exists:users,id'],
            'admission_reason' => ['required', 'string'],
        ];
    }
}
