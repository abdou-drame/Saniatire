<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $structureId = $this->route('structure')?->id;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('structures', 'code')->ignore($structureId)],
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
        ];
    }
}
