<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bank_id'          => 'nullable|exists:banks,id',
            'asset_id'         => 'nullable|exists:assets,id',
            'category_id'      => 'required|exists:categories,id',
            'amount'           => 'required|numeric|min:0',
            'transaction_date' => 'required|date_format:d-m-Y H:i:s',
            'description'      => 'nullable|string',
        ];
    }
}
