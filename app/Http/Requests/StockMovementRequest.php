<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // ajustement is a signed correction delta (can reduce or increase
        // stock); every other movement type is expressed as a positive
        // magnitude, so the "quantite" rule differs by type.
        $quantiteRule = $this->input('type') === 'ajustement' ? ['not_in:0'] : ['min:1'];

        return [
            'product_batch_id' => ['required', 'integer', 'exists:product_batches,id'],
            'site_id' => ['required', 'integer', 'exists:sites,id'],
            'destination_site_id' => ['required_if:type,transfert', 'nullable', 'integer', 'exists:sites,id', 'different:site_id'],
            'type' => ['required', 'in:entree,sortie,ajustement,transfert'],
            'quantite' => ['required', 'integer', ...$quantiteRule],
            'motif' => ['nullable', 'string', 'max:255'],
            'dispensed_for_type' => ['nullable', 'in:consultation,hospitalization'],
            'dispensed_for_id' => ['required_with:dispensed_for_type', 'nullable', 'integer'],
        ];
    }
}
