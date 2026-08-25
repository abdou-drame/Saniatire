<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InsuranceConventionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'insurance_provider_id' => [$sometimesOnUpdate, 'integer', 'exists:insurance_providers,id'],
            'nom' => [$sometimesOnUpdate, 'string', 'max:255'],
            'date_debut' => [$sometimesOnUpdate, 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }
}
