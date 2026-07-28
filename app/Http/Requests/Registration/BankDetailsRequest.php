<?php

namespace App\Http\Requests\Registration;

use Illuminate\Foundation\Http\FormRequest;

class BankDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_uuid' => strtoupper(trim((string) $this->input('user_uuid'))),
            'account_name' => trim((string) $this->input('account_name')),
            'bank_name' => trim((string) $this->input('bank_name')),
            'branch_name' => trim((string) $this->input('branch_name')),
            'account_number' => trim((string) $this->input('account_number')),
            'bank_code' => filled($this->input('bank_code'))
                ? trim((string) $this->input('bank_code'))
                : null,
            'branch_code' => filled($this->input('branch_code'))
                ? trim((string) $this->input('branch_code'))
                : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'user_uuid' => ['required', 'string', 'size:10'],
            'account_name' => ['required', 'string', 'max:150'],
            'bank_name' => ['required', 'string', 'max:150'],
            'branch_name' => ['required', 'string', 'max:150'],
            'bank_code' => ['nullable', 'string', 'max:30'],
            'branch_code' => ['nullable', 'string', 'max:30'],
            'account_number' => ['required', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_uuid.required' => 'Registration session is required.',
            'user_uuid.size' => 'Invalid registration session.',
            'account_name.required' => 'Account name is required.',
            'bank_name.required' => 'Bank name is required.',
            'branch_name.required' => 'Branch name is required.',
            'account_number.required' => 'Account number is required.',
        ];
    }
}
