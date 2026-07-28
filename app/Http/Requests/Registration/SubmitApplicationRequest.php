<?php

namespace App\Http\Requests\Registration;

use Illuminate\Foundation\Http\FormRequest;

class SubmitApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_uuid' => strtoupper(trim((string) $this->input('user_uuid'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'user_uuid' => ['required', 'string', 'size:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_uuid.required' => 'Registration session is required.',
            'user_uuid.size' => 'Invalid registration session.',
        ];
    }
}
