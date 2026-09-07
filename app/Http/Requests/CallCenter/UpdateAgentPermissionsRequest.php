<?php

namespace App\Http\Requests\CallCenter;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the Call Center Agent operational permission payload.
 *
 * Accepts permission slugs or permission IDs. Management permissions
 * and other-portal permissions are rejected in AgentService.
 */
class UpdateAgentPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'permissions' => $this->normalizeIdentifierList($this->input('permissions', [])),
            'denied' => $this->normalizeIdentifierList($this->input('denied', [])),
        ]);
    }

    public function rules(): array
    {
        return [
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['required'],
            'denied' => ['nullable', 'array'],
            'denied.*' => ['required'],
        ];
    }

    public function messages(): array
    {
        return [
            'permissions.array' => 'Permissions must be provided as a list.',
            'denied.array' => 'Denied permissions must be provided as a list.',
        ];
    }

    /**
     * Selected permissions that should be granted (allowed = true).
     *
     * @return list<int|string>
     */
    public function grantedIdentifiers(): array
    {
        return $this->validated()['permissions'] ?? [];
    }

    /**
     * Permissions that should be explicitly denied (allowed = false).
     *
     * @return list<int|string>
     */
    public function deniedIdentifiers(): array
    {
        return $this->validated()['denied'] ?? [];
    }

    /**
     * @return list<int|string>
     */
    private function normalizeIdentifierList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];

        foreach ($value as $item) {
            if (is_int($item)) {
                $normalized[] = $item;

                continue;
            }

            if (! is_string($item) && ! is_numeric($item)) {
                continue;
            }

            $string = trim((string) $item);

            if ($string === '') {
                continue;
            }

            $normalized[] = ctype_digit($string) ? (int) $string : $string;
        }

        return array_values($normalized);
    }
}
