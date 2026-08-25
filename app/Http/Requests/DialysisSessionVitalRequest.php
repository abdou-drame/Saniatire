<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DialysisSessionVitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'measured_at' => ['required', 'date'],
            'blood_pressure_systolic' => ['required', 'integer', 'min:0', 'max:300'],
            'blood_pressure_diastolic' => ['required', 'integer', 'min:0', 'max:200'],
            'heart_rate' => ['required', 'integer', 'min:0', 'max:250'],
        ];
    }
}
