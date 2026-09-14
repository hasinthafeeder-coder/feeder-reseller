<?php

namespace App\Http\Requests\Order;

use Feeder\Core\Enums\OrderCommentContextType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderCommentRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:5000'],
            'context_type' => [
                'required',
                'string',
                Rule::enum(OrderCommentContextType::class)->except([OrderCommentContextType::SYSTEM]),
            ],
        ];
    }
}
