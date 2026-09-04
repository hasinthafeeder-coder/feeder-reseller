<?php

namespace App\Http\Requests\Product;

use Feeder\Core\Enums\SupplierType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexResellerProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'suppliers' => ['nullable', 'array'],
            'suppliers.*' => ['integer'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'max:255'],
            'supplier_types' => ['nullable', 'array'],
            'supplier_types.*' => ['string', Rule::in([
                SupplierType::PRO->value,
                SupplierType::STANDARD->value,
            ])],
        ];
    }
}
