<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', 'exists:invoices,id'],
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'mode_paiement' => ['required', 'in:especes,carte,virement,mobile_money'],
            'reference_transaction' => ['nullable', 'string', 'max:255'],
            'statut_mobile_money' => ['nullable', 'in:pending,confirmed,failed'],
            'montant' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
