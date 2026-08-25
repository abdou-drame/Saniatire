<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupplierRequest extends FormRequest
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
            'contact' => ['nullable', 'string', 'max:255'],
            'conditions_commerciales' => ['nullable', 'string'],
        ];
    }
}
