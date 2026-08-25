<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaternityPrenatalVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visit_number' => ['required', 'integer', 'min:1'],
            'gestational_age_weeks' => ['required', 'integer', 'min:0', 'max:45'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'blood_pressure_systolic' => ['nullable', 'integer', 'min:0', 'max:300'],
            'blood_pressure_diastolic' => ['nullable', 'integer', 'min:0', 'max:200'],
            'fundal_height_cm' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'fetal_movements' => ['nullable', 'string', 'max:255'],
            'fetal_heart_rate' => ['nullable', 'integer', 'min:0', 'max:250'],
            'visit_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
