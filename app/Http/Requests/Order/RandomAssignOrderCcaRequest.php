<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class RandomAssignOrderCcaRequest extends FormRequest
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
            'cca_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:500'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cca_id.required' => 'Select a call center agent.',
            'quantity.required' => 'Enter how many orders to assign.',
            'quantity.min' => 'Quantity must be at least 1.',
            'quantity.max' => 'Quantity may not be greater than 500.',
        ];
    }
}
