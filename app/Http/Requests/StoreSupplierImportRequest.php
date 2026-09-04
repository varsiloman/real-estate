<?php

namespace App\Http\Requests;

use App\Enums\OfferStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'offers' => ['present', 'array'],
            'offers.*.external_offer_id' => ['required', 'string', 'max:255', 'distinct:strict'],
            'offers.*.property.external_code' => ['required', 'string', 'max:255'],
            'offers.*.property.name' => ['required', 'string', 'max:255'],
            'offers.*.status' => ['required', Rule::enum(OfferStatus::class)],
            'offers.*.price_amount' => ['required', 'numeric', 'gt:0'],
            'offers.*.currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'offers.*.check_in_date' => ['required', 'date', 'before:offers.*.check_out_date'],
            'offers.*.check_out_date' => ['required', 'date'],
            'offers.*.valid_from' => ['nullable', 'date', 'before_or_equal:offers.*.valid_until'],
            'offers.*.valid_until' => ['required', 'date'],
        ];
    }
}
