<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
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
            'category_id'      => 'sometimes|exists:categories,id',
            'amount'           => 'sometimes|numeric|min:0',
            'transaction_date' => 'sometimes|date_format:d-m-Y H:i:s',
            'description'      => 'nullable|string',
        ];
    }
}
