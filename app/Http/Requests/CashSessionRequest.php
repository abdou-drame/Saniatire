<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'montant_ouverture' => ['required', 'numeric', 'min:0'],
        ];
    }
}
