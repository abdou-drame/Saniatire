<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'generic_catalog_ref' => ['nullable', 'string', 'max:255'],
            'nom_commercial' => [$sometimesOnUpdate, 'string', 'max:255'],
            'dci' => [$sometimesOnUpdate, 'string', 'max:255'],
            'forme_galenique' => [$sometimesOnUpdate, 'string', 'max:255'],
            'dosage' => ['nullable', 'string', 'max:255'],
            'categorie' => [$sometimesOnUpdate, 'in:medicament,consommable,dispositif_medical'],
            'unite_vente' => [$sometimesOnUpdate, 'string', 'max:255'],
            'actif' => ['sometimes', 'boolean'],
        ];
    }
}
