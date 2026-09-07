<?php

namespace App\Http\Requests\CallCenter;

use Feeder\Core\Models\User;
use Feeder\Core\Services\CountryRegistrationRuleService;
use Feeder\Core\Validation\Rules\ValidCountryIdentityDocument;
use Feeder\Core\Validation\Rules\ValidCountryPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateAgentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $rules = app(CountryRegistrationRuleService::class)->resolveForResellerRegistration();

        $normalizedPhone = $rules->normalizePhone((string) $this->input('phone'));
        if ($normalizedPhone !== null) {
            $this->merge(['phone' => $normalizedPhone]);
        }

        $password = $this->input('password');
        if ($password === null || (is_string($password) && trim($password) === '')) {
            $this->merge([
                'password' => null,
                'password_confirmation' => null,
            ]);
        }

        $this->merge([
            'first_name' => trim((string) $this->input('first_name')),
            'last_name' => trim((string) $this->input('last_name')),
            'nic' => $this->normalizeOptionalNic(
                $rules->normalizeIdentityDocument((string) $this->input('nic', ''))
            ),
        ]);
    }

    public function rules(): array
    {
        $countryRules = app(CountryRegistrationRuleService::class)->resolveForResellerRegistration();
        $agentUserId = $this->routeAgent()?->id;
        $agentProfileId = $this->routeAgent()?->profile?->id;

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => [
                'required',
                'string',
                new ValidCountryPhone($countryRules),
                Rule::unique('users', 'phone')
                    ->whereNull('deleted_at')
                    ->ignore($agentUserId),
            ],
            'nic' => [
                'nullable',
                'string',
                'max:12',
                new ValidCountryIdentityDocument($countryRules),
                Rule::unique('user_profiles', 'nic')->ignore($agentProfileId),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $phone = (string) $this->input('phone', '');
            $agentUserId = $this->routeAgent()?->id;

            if (
                $phone !== ''
                && ! $validator->errors()->has('phone')
                && User::query()
                    ->where('email', sprintf('%s@reseller.local', $phone))
                    ->when($agentUserId !== null, fn ($query) => $query->where('id', '!=', $agentUserId))
                    ->exists()
            ) {
                $validator->errors()->add('phone', 'This phone number is already registered.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'phone.required' => 'Phone number is required.',
            'phone.unique' => 'This phone number is already registered.',
            'nic.unique' => 'This identity document number is already registered.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }

    /**
     * Payload for AgentService::update().
     *
     * System-controlled and deferred-phase fields are intentionally excluded:
     * company_id, user_type, role_id, status, agent_commission_per_order,
     * permissions, email, uuid, user/profile ids.
     *
     * @return array{
     *     first_name: string,
     *     last_name: string,
     *     phone: string,
     *     nic: string|null,
     *     password?: string
     * }
     */
    public function agentPayload(): array
    {
        $validated = $this->validated();

        $payload = [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'],
            'nic' => $validated['nic'] ?? null,
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        return $payload;
    }

    private function routeAgent(): ?User
    {
        $uuid = (string) $this->route('agent');

        if ($uuid === '') {
            return null;
        }

        return User::query()
            ->where('uuid', $uuid)
            ->with('profile')
            ->first();
    }

    private function normalizeOptionalNic(string $normalized): ?string
    {
        return $normalized === '' ? null : $normalized;
    }
}
