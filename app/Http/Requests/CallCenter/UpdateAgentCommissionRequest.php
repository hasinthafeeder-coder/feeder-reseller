<?php

namespace App\Http\Requests\CallCenter;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the Call Center Agent current commission configuration only.
 *
 * Field name matches AgentService / create-agent convention:
 * agent_commission_per_order
 */
class UpdateAgentCommissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $commission = $this->input(
            'agent_commission_per_order',
            $this->input('commission', $this->input('commission_rate'))
        );

        $this->merge([
            'agent_commission_per_order' => is_string($commission)
                ? trim($commission)
                : $commission,
        ]);
    }

    public function rules(): array
    {
        return [
            'agent_commission_per_order' => [
                'required',
                'numeric',
                'min:0',
                'max:100000000',
                'regex:/^\d+(\.\d{1,2})?$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'agent_commission_per_order.required' => 'Agent commission per order is required.',
            'agent_commission_per_order.numeric' => 'Agent commission per order must be a valid numeric value.',
            'agent_commission_per_order.min' => 'Agent commission per order cannot be negative.',
            'agent_commission_per_order.max' => 'Agent commission per order exceeds the supported maximum.',
            'agent_commission_per_order.regex' => 'Agent commission per order must have at most 2 decimal places.',
        ];
    }

    /**
     * Commission amount for AgentService::updateCommission().
     */
    public function commissionAmount(): mixed
    {
        return $this->validated()['agent_commission_per_order'];
    }
}
