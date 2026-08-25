<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockThresholdRequest extends FormRequest
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
            'seuil_minimum' => [$sometimesOnUpdate, 'integer', 'min:0'],
        ];
    }
}
