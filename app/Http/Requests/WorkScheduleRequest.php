<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'user_id' => [$sometimesOnUpdate, 'integer', 'exists:users,id'],
            'site_id' => [$sometimesOnUpdate, 'integer', 'exists:sites,id'],
            'jour_semaine' => ['nullable', 'integer', 'between:0,6', 'required_without:date'],
            'date' => ['nullable', 'date', 'required_without:jour_semaine'],
            'heure_debut' => [$sometimesOnUpdate, 'date_format:H:i'],
            'heure_fin' => [$sometimesOnUpdate, 'date_format:H:i', 'after:heure_debut'],
            'type' => ['sometimes', Rule::in(['normal', 'garde', 'astreinte'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('jour_semaine') && $this->filled('date')) {
                $validator->errors()->add('date', "Renseignez soit un jour de semaine récurrent, soit une date précise, pas les deux.");
            }
        });
    }
}
