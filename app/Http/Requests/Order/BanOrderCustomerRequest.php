<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class BanOrderCustomerRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Please enter a ban reason.',
            'reason.min' => 'Ban reason must be at least 3 characters.',
        ];
    }
}
