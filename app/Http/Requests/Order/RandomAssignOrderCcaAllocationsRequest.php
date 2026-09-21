<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RandomAssignOrderCcaAllocationsRequest extends FormRequest
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
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.cca_id' => ['required', 'integer'],
            'allocations.*.quantity' => ['required', 'integer', 'min:0', 'max:500'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'allocations.required' => 'Enter quantities for one or more call center agents.',
            'allocations.*.cca_id.required' => 'Select a valid call center agent.',
            'allocations.*.quantity.required' => 'Enter how many orders to assign.',
            'allocations.*.quantity.min' => 'Quantity must be zero or greater.',
            'allocations.*.quantity.max' => 'Quantity may not be greater than 500.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allocations = $this->input('allocations', []);
            if (! is_array($allocations)) {
                return;
            }

            $positive = 0;
            $seen = [];

            foreach ($allocations as $index => $allocation) {
                if (! is_array($allocation)) {
                    continue;
                }

                $ccaId = (int) ($allocation['cca_id'] ?? 0);
                $quantity = (int) ($allocation['quantity'] ?? 0);

                if ($quantity > 0) {
                    $positive++;
                }

                if ($ccaId > 0 && isset($seen[$ccaId])) {
                    $validator->errors()->add(
                        'allocations.'.$index.'.cca_id',
                        'Each call center agent may appear only once.'
                    );
                }

                if ($ccaId > 0) {
                    $seen[$ccaId] = true;
                }
            }

            if ($positive < 1) {
                $validator->errors()->add(
                    'allocations',
                    'Enter a quantity of at least 1 for one or more call center agents.'
                );
            }
        });
    }
}
