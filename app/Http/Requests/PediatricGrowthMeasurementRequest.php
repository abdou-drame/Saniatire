<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PediatricGrowthMeasurementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'measured_at' => ['required', 'date'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:250'],
            'head_circumference_cm' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
