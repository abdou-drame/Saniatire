<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MentalHealthScaleScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scale_name' => ['required', 'string', 'max:255'],
            // Generic sanity bound: the model deliberately doesn't hardcode
            // a per-scale range (PHQ-9, HAD, MADRS, ... each differ), so
            // this only rejects clearly out-of-range/negative input.
            'score' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'scored_at' => ['required', 'date'],
        ];
    }
}
