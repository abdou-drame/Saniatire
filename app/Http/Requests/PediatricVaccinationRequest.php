<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PediatricVaccinationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vaccine_name' => ['required', 'string', 'max:255'],
            'dose_number' => ['nullable', 'integer', 'min:1', 'max:20'],
            'administered_at' => ['required', 'date'],
        ];
    }
}
