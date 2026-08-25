<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class QuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.libelle' => ['required', 'string', 'max:255'],
            'items.*.categorie' => ['required', 'string', 'max:255'],
            'items.*.quantite' => ['required', 'integer', 'min:1'],
            'items.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
        ];
    }
}
