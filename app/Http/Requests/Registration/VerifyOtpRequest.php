<?php

namespace App\Http\Requests\Registration;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => preg_replace('/\D+/', '', (string) $this->input('phone')),
            'otp' => preg_replace('/\D+/', '', (string) $this->input('otp')),
        ]);
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'digits:10'],
            'otp' => ['required', 'digits_between:4,6'],
        ];
    }
}