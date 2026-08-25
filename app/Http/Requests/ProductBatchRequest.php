<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'product_id' => [$sometimesOnUpdate, 'integer', 'exists:products,id'],
            'site_id' => [$sometimesOnUpdate, 'integer', 'exists:sites,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'numero_lot' => [$sometimesOnUpdate, 'string', 'max:255'],
            'date_peremption' => [$sometimesOnUpdate, 'date'],
            'quantite_stock' => [$sometimesOnUpdate, 'integer', 'min:0'],
            'prix_achat_unitaire' => [$sometimesOnUpdate, 'numeric', 'min:0'],
        ];
    }
}
