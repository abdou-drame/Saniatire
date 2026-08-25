<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImagingReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $sometimesOnUpdate = $this->isMethod('put') || $this->isMethod('patch') ? 'sometimes' : 'required';

        return [
            'content' => [$sometimesOnUpdate, 'string'],
        ];
    }
}
