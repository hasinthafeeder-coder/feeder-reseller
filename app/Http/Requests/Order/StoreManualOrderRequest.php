<?php

namespace App\Http\Requests\Order;

use App\Services\Order\ResellerManualOrderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('duplicate_warning_overridden')) {
            $this->merge([
                'duplicate_warning_overridden' => filter_var(
                    $this->input('duplicate_warning_overridden'),
                    FILTER_VALIDATE_BOOLEAN
                ),
            ]);
        }

        if ($this->has('after_hours_warning_shown')) {
            $this->merge([
                'after_hours_warning_shown' => filter_var(
                    $this->input('after_hours_warning_shown'),
                    FILTER_VALIDATE_BOOLEAN
                ),
            ]);
        }

        // Never accept browser-trusted ownership, pricing, or CCA assignment.
        $this->request->remove('reseller_id');
        $this->request->remove('reseller_company_id');
        $this->request->remove('cca_id');
        $this->request->remove('customer_id');
        $this->request->remove('unit_selling_price');
        $this->request->remove('courier_fee_amount');
        $this->request->remove('order_type');
    }

    public function rules(): array
    {
        return [
            'market_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'customer_name' => ['required', 'string', 'max:255'],
            'primary_phone' => ['required', 'string', 'max:40'],
            'secondary_phone' => ['nullable', 'string', 'max:40'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city_name' => ['nullable', 'string', 'max:120'],
            'district_name' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:40'],
            'full_address_text' => ['nullable', 'string', 'max:1000'],
            'courier_id' => ['nullable', 'integer', 'min:1'],
            'courier_service_id' => ['nullable', 'integer', 'min:1'],
            'courier_city_id' => ['nullable', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'items.*.selected_selling_price' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'duplicate_warning_overridden' => ['sometimes', 'boolean'],
            'after_hours_warning_shown' => ['sometimes', 'boolean'],
            'intent' => [
                'nullable',
                'string',
                Rule::in([
                    ResellerManualOrderService::INTENT_CONFIRM,
                    ResellerManualOrderService::INTENT_SEND_TO_CALL_CENTER,
                ]),
            ],
            'assignment_target' => [
                'nullable',
                'string',
                Rule::in([
                    ResellerManualOrderService::ASSIGNMENT_UNASSIGNED,
                    ResellerManualOrderService::ASSIGNMENT_POOL,
                    ResellerManualOrderService::ASSIGNMENT_CCA,
                ]),
            ],
            'assign_cca_id' => [
                'nullable',
                'integer',
                'required_if:assignment_target,'.ResellerManualOrderService::ASSIGNMENT_CCA,
            ],
        ];
    }

    /**
     * @return array{
     *     market_id: int,
     *     supplier_id: int,
     *     customer_name: string,
     *     primary_phone: string,
     *     secondary_phone: ?string,
     *     address_line1: string,
     *     address_line2: ?string,
     *     city_name: ?string,
     *     district_name: ?string,
     *     postal_code: ?string,
     *     full_address_text: ?string,
     *     courier_id: ?int,
     *     courier_service_id: ?int,
     *     courier_city_id: ?int,
     *     items: list<array{product_variant_id: int, quantity: int, selected_selling_price?: float|null}>,
     *     discount_amount: float,
     *     duplicate_warning_overridden: bool,
     *     after_hours_warning_shown: bool,
     *     intent: string,
     *     assignment_target: string,
     *     assign_cca_id: ?int
     * }
     */
    public function orderPayload(): array
    {
        $validated = $this->validated();

        $items = [];
        foreach ($validated['items'] as $item) {
            $line = [
                'product_variant_id' => (int) $item['product_variant_id'],
                'quantity' => (int) $item['quantity'],
            ];

            if (array_key_exists('selected_selling_price', $item) && $item['selected_selling_price'] !== null) {
                $line['selected_selling_price'] = round((float) $item['selected_selling_price'], 2);
            }

            $items[] = $line;
        }

        return [
            'market_id' => (int) $validated['market_id'],
            'supplier_id' => (int) $validated['supplier_id'],
            'customer_name' => trim((string) $validated['customer_name']),
            'primary_phone' => trim((string) $validated['primary_phone']),
            'secondary_phone' => filled($validated['secondary_phone'] ?? null)
                ? trim((string) $validated['secondary_phone'])
                : null,
            'address_line1' => trim((string) $validated['address_line1']),
            'address_line2' => filled($validated['address_line2'] ?? null)
                ? trim((string) $validated['address_line2'])
                : null,
            'city_name' => filled($validated['city_name'] ?? null)
                ? trim((string) $validated['city_name'])
                : null,
            'district_name' => filled($validated['district_name'] ?? null)
                ? trim((string) $validated['district_name'])
                : null,
            'postal_code' => filled($validated['postal_code'] ?? null)
                ? trim((string) $validated['postal_code'])
                : null,
            'full_address_text' => filled($validated['full_address_text'] ?? null)
                ? trim((string) $validated['full_address_text'])
                : null,
            'courier_id' => isset($validated['courier_id']) ? (int) $validated['courier_id'] : null,
            'courier_service_id' => isset($validated['courier_service_id'])
                ? (int) $validated['courier_service_id']
                : null,
            'courier_city_id' => isset($validated['courier_city_id'])
                ? (int) $validated['courier_city_id']
                : null,
            'items' => $items,
            'discount_amount' => round((float) ($validated['discount_amount'] ?? 0), 2),
            'duplicate_warning_overridden' => (bool) ($validated['duplicate_warning_overridden'] ?? false),
            'after_hours_warning_shown' => (bool) ($validated['after_hours_warning_shown'] ?? false),
            'intent' => (string) ($validated['intent'] ?? ResellerManualOrderService::INTENT_SEND_TO_CALL_CENTER),
            'assignment_target' => (string) ($validated['assignment_target'] ?? ResellerManualOrderService::ASSIGNMENT_UNASSIGNED),
            'assign_cca_id' => isset($validated['assign_cca_id']) ? (int) $validated['assign_cca_id'] : null,
        ];
    }
}
