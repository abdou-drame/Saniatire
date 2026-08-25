<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExternalPrescriberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'nom' => [$sometimesOnUpdate, 'string', 'max:255'],
            'specialite' => ['nullable', 'string', 'max:255'],
            'email' => [
                $sometimesOnUpdate, 'email', 'max:255',
                Rule::unique('external_prescribers', 'email')->ignore($this->route('externalPrescriber')),
            ],
            'telephone' => ['nullable', 'string', 'max:50'],
            'statut' => ['sometimes', Rule::in(['actif', 'inactif'])],
        ];
    }
}
