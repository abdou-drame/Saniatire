<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CashSessionCloseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'montant_cloture' => ['required', 'numeric', 'min:0'],
        ];
    }
}
