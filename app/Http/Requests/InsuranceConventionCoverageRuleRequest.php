<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InsuranceConventionCoverageRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'categorie' => [$sometimesOnUpdate, 'string', 'max:255'],
            'taux_couverture' => [$sometimesOnUpdate, 'numeric', 'min:0', 'max:100'],
            'plafond_montant' => ['nullable', 'numeric', 'min:0'],
            'exclu' => ['sometimes', 'boolean'],
        ];
    }
}
