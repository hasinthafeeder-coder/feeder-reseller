<?php

namespace App\Http\Requests\Registration;

use Feeder\Core\Services\CountryRegistrationRuleService;
use Feeder\Core\Validation\Rules\ValidCountryPhone;
use Illuminate\Foundation\Http\FormRequest;

class VerifyPhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $rules = app(CountryRegistrationRuleService::class)->resolveForResellerRegistration();
        $normalizedPhone = $rules->normalizePhone((string) $this->input('phone'));

        if ($normalizedPhone !== null) {
            $this->merge(['phone' => $normalizedPhone]);
        }
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
        ];
    }
}
