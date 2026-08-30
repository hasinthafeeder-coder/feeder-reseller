<?php

namespace App\Http\Requests\Registration;

use Feeder\Core\Services\CountryRegistrationRuleService;
use Feeder\Core\Validation\Rules\ValidCountryPhone;
use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $rules = app(CountryRegistrationRuleService::class)->resolveForResellerRegistration();
        $normalizedPhone = $rules->normalizePhone((string) $this->input('phone'));

        $this->merge([
            'otp' => preg_replace('/\D+/', '', (string) $this->input('otp')),
            'phone' => $normalizedPhone ?? preg_replace('/\D+/', '', (string) $this->input('phone')),
        ]);
    }

    public function rules(): array
    {
        $rules = app(CountryRegistrationRuleService::class)->resolveForResellerRegistration();

        return [
            'phone' => [
                'required',
                'string',
                new ValidCountryPhone($rules),
            ],
            'otp' => ['required', 'digits_between:4,6'],
        ];
    }
}
