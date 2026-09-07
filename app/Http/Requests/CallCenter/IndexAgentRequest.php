<?php

namespace App\Http\Requests\CallCenter;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['', 'active', 'inactive'])],
        ];
    }

    public function search(): string
    {
        return trim((string) $this->input('search', ''));
    }

    public function statusFilter(): string
    {
        return strtolower(trim((string) $this->input('status', '')));
    }
}
