<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EmployeeProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'user_id' => [$sometimesOnUpdate, 'integer', 'exists:users,id'],
            'date_embauche' => ['nullable', 'date'],
            'type_contrat' => ['nullable', 'string', 'max:50'],
            'statut_emploi' => ['sometimes', Rule::in(['actif', 'en_conge', 'suspendu', 'termine'])],
            'qualification' => ['nullable', 'string', 'max:255'],
            'numero_ordre' => ['nullable', 'string', 'max:100'],
        ];
    }
}
