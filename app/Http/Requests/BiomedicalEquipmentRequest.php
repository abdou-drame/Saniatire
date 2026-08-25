<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BiomedicalEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'site_id' => [$sometimesOnUpdate, 'integer', 'exists:sites,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'nom' => [$sometimesOnUpdate, 'string', 'max:255'],
            'categorie' => [$sometimesOnUpdate, 'string', 'max:255'],
            'numero_serie' => [$sometimesOnUpdate, 'string', 'max:255'],
            'date_acquisition' => [$sometimesOnUpdate, 'date'],
            'date_fin_garantie' => ['nullable', 'date'],
            'statut' => ['sometimes', 'in:en_service,en_maintenance,hors_service,reforme'],
        ];
    }
}
