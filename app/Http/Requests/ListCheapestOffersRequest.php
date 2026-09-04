<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class ListCheapestOffersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $currency = $this->input('currency');

        if (is_string($currency)) {
            $this->merge([
                'currency' => Str::upper(trim($currency)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'check_in_date' => ['required', 'date', 'before:check_out_date'],
            'check_out_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
        ];
    }
}
