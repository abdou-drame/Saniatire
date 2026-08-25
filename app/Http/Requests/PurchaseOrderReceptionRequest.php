<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderReceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantite_recue' => ['required', 'integer', 'min:1'],
            'date_reception' => ['required', 'date'],
            'controle_qualite' => ['required', 'in:conforme,non_conforme'],
            'numero_lot' => ['required', 'string', 'max:255'],
            'date_peremption' => ['required', 'date'],
        ];
    }
}
