<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reprend exactement les règles de StructureRequest pour le bloc structure
 * (mêmes champs que le modèle structures du socle, cf. StructureRequest),
 * plus le premier compte administrateur créé dans la même transaction —
 * voir PlatformStructureController::store().
 */
class PlatformStructureStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('structures', 'code')],
            'legal_name' => ['required', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in([
                'cabinet', 'centre_specialise', 'laboratoire', 'imagerie', 'clinique', 'polyclinique', 'groupe_sante',
            ])],
            'logo_path' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'opening_hours' => ['nullable', 'array'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'color_primary' => ['nullable', 'string', 'max:7'],
            'color_secondary' => ['nullable', 'string', 'max:7'],
            'currency' => ['nullable', 'string', 'size:3'],
            'locale' => ['nullable', 'string', 'max:5'],
            'is_active' => ['boolean'],

            'admin_first_name' => ['required', 'string', 'max:255'],
            'admin_last_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
        ];
    }
}
