<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MaternityPartogramReadingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recorded_at' => ['required', 'date'],
            'cervical_dilation_cm' => ['required', 'numeric', 'min:0', 'max:10'],
            'fetal_heart_rate' => ['nullable', 'integer', 'min:0', 'max:250'],
            'contractions_per_10min' => ['nullable', 'integer', 'min:0', 'max:10'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
