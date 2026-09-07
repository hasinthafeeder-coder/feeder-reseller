<?php

namespace App\Http\Requests\CallCenter;

use App\Services\CallCenter\AgentService;
use Feeder\Core\Models\User;
use Feeder\Core\Services\CountryRegistrationRuleService;
use Feeder\Core\Validation\Rules\ValidCountryIdentityDocument;
use Feeder\Core\Validation\Rules\ValidCountryPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class StoreAgentRequest extends FormRequest
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

        $commission = $this->input('agent_commission_per_order', $this->input('commission'));
        $this->merge([
            'agent_commission_per_order' => is_string($commission) ? trim($commission) : $commission,
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

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => [
                'required',
                'string',
                new ValidCountryPhone($countryRules),
                Rule::unique('users', 'phone')->whereNull('deleted_at'),
            ],
            'nic' => [
                'nullable',
                'string',
                'max:12',
                new ValidCountryIdentityDocument($countryRules),
                Rule::unique('user_profiles', 'nic'),
            ],
            'password' => ['required', 'confirmed', Password::defaults()],
            'agent_commission_per_order' => [
                'required',
                'numeric',
                'min:0',
                'max:100000000',
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:191'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $phone = (string) $this->input('phone', '');

            if (
                $phone !== ''
                && ! $validator->errors()->has('phone')
                && User::query()->where('email', sprintf('%s@reseller.local', $phone))->exists()
            ) {
                $validator->errors()->add('phone', 'This phone number is already registered.');
            }

            $permissions = $this->input('permissions', []);

            if (! is_array($permissions)) {
                return;
            }

            foreach ($permissions as $permission) {
                $slug = (string) $permission;

                if (
                    str_starts_with($slug, 'call_center.agents.')
                    || in_array($slug, AgentService::MANAGEMENT_PERMISSION_SLUGS, true)
                ) {
                    $validator->errors()->add(
                        'permissions',
                        'Call Center Agent management permissions cannot be assigned to Agents.'
                    );

                    return;
                }
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
            'password.required' => 'Password is required.',
            'password.confirmed' => 'Password confirmation does not match.',
            'agent_commission_per_order.required' => 'Agent commission per order is required.',
            'agent_commission_per_order.numeric' => 'Agent commission per order must be a valid numeric value.',
            'agent_commission_per_order.min' => 'Agent commission per order cannot be negative.',
        ];
    }

    /**
     * Payload for AgentService::create().
     *
     * System-controlled fields (company_id, role_id, user_type, status)
     * are intentionally excluded. Preview-only permission keys are ignored
     * by AgentService; real RESELLER operational permissions are persisted.
     *
     * @return array{
     *     first_name: string,
     *     last_name: string,
     *     phone: string,
     *     password: string,
     *     nic: string|null,
     *     agent_commission_per_order: mixed,
     *     permissions: list<string>
     * }
     */
    public function agentPayload(): array
    {
        $validated = $this->validated();

        return [
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'phone' => $validated['phone'],
            'password' => $validated['password'],
            'nic' => $validated['nic'] ?? null,
            'agent_commission_per_order' => $validated['agent_commission_per_order'],
            'permissions' => array_values($validated['permissions'] ?? []),
        ];
    }

    private function normalizeOptionalNic(string $normalized): ?string
    {
        return $normalized === '' ? null : $normalized;
    }
}
