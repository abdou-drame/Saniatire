<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantite' => ['required', 'integer', 'min:1'],
        ];
    }
}
