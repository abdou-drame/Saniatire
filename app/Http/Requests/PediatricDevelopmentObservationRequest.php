<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PediatricDevelopmentObservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'age_months' => ['required', 'integer', 'min:0', 'max:216'],
            'observation' => ['required', 'string'],
            'observed_at' => ['required', 'date'],
        ];
    }
}
