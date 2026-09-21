<?php

namespace App\Http\Requests\Order;

use Feeder\Core\Enums\OrderAssignmentState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'order_number' => ['nullable', 'string', 'max:64'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', 'string'],
            'supplier_id' => ['nullable', 'integer'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'date_preset' => ['nullable', 'string', Rule::in(['all', 'today', 'yesterday', 'last7', 'last30', 'custom'])],
            'cca_id' => ['nullable', 'integer'],
            'source' => ['nullable', 'string'],
            'assignment' => [
                'nullable',
                'string',
                Rule::in(['all', 'my', ...array_column(OrderAssignmentState::cases(), 'value')]),
            ],
            'tab' => [
                'nullable',
                'string',
                Rule::in(['all', 'company', 'my', ...array_column(OrderAssignmentState::cases(), 'value')]),
            ],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'workspace' => [
                'nullable',
                'string',
                Rule::in(['new', 'import', 'call-center', 'archived']),
            ],
            'archive' => [
                'nullable',
                'string',
                Rule::in(['completed', 'returned', 'expired']),
            ],
            'json' => ['nullable', 'boolean'],
        ];
    }
}
