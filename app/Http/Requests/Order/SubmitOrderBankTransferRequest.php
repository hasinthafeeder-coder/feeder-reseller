<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class SubmitOrderBankTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_slip' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'reference_number' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }
}
