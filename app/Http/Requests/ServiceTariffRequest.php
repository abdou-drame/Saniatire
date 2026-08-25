<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceTariffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'code' => [$sometimesOnUpdate, 'string', 'max:255'],
            'libelle' => [$sometimesOnUpdate, 'string', 'max:255'],
            'categorie' => [$sometimesOnUpdate, 'string', 'max:255'],
            'prix_unitaire' => [$sometimesOnUpdate, 'numeric', 'min:0'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }
}
