<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class InsuranceProviderRequest extends FormRequest
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
            'type' => [$sometimesOnUpdate, 'in:assurance_privee,ipm,mutuelle'],
            'contact' => ['nullable', 'string', 'max:255'],
        ];
    }
}
