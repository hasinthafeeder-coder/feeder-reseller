<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class IndexFinancialOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasPermission('dashboard.view');
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{date_from: string, date_to: string}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'date_from' => is_string($validated['date_from'] ?? null) ? $validated['date_from'] : '',
            'date_to' => is_string($validated['date_to'] ?? null) ? $validated['date_to'] : '',
        ];
    }
}
