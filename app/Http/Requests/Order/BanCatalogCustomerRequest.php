<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class BanCatalogCustomerRequest extends FormRequest
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
            'market_id' => ['required', 'integer', 'min:1'],
            'customer_name' => ['required', 'string', 'max:191'],
            'primary_phone' => ['required', 'string', 'max:40'],
            'secondary_phone' => ['nullable', 'string', 'max:40'],
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'market_id.required' => 'Select a market before banning a customer.',
            'customer_name.required' => 'Customer name is required to ban.',
            'primary_phone.required' => 'Phone 1 is required to ban a customer.',
            'reason.required' => 'Please enter a ban reason.',
            'reason.min' => 'Ban reason must be at least 3 characters.',
        ];
    }
}
