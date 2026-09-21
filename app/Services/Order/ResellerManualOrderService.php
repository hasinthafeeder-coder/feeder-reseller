<?php

namespace App\Services\Order;
use Feeder\Core\Enums\OrderCcaAssignmentOrigin;
use Feeder\Core\Enums\OrderSource;
use Feeder\Core\Enums\OrderType;
use Feeder\Core\Exceptions\ConflictingCustomerIdentityException;
use Feeder\Core\Exceptions\DuplicateOrderWarningException;
use Feeder\Core\Models\Market;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\ProductVariant;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Order\CallCenterAgentEligibilityService;
use Feeder\Core\Services\Order\OrderCcaAssignmentService;
use Feeder\Core\Services\Order\OrderCourierLookupService;
use Feeder\Core\Services\Order\OrderService;
use Feeder\Core\Support\ResellerProductPricing;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Reseller Portal write adapter for manual order creation.
 *
 * Controllers pass authenticated actor + validated request shape.
 * Reseller / company IDs are taken from the authenticated user only.
 * Selling prices are resolved from ProductVariant - never trusted from the browser.
 * CCA identity for auto-assignment is taken from the authenticated actor only.
 *
 * Confirm Order and Send to Call Center both create NEW + PENDING.
 * Confirm does not transition to CONFIRMED and does not book a shipment.
 */

class ResellerManualOrderService
{
    public const INTENT_CONFIRM = 'confirm';
    public const INTENT_SEND_TO_CALL_CENTER = 'send_to_call_center';
    public const ASSIGNMENT_UNASSIGNED = 'unassigned';
    public const ASSIGNMENT_POOL = 'pool';
    public const ASSIGNMENT_CCA = 'cca';

    public function __construct(
        private readonly OrderService $orderService,
        private readonly OrderCcaAssignmentService $ccaAssignmentService,
        private readonly CallCenterAgentEligibilityService $ccaEligibilityService,
        private readonly OrderCourierLookupService $courierLookupService,
    ) {}

    /**
     * @param  array{
     *     market_id: int,
     *     supplier_id: int,
     *     customer_name: string,
     *     primary_phone: string,
     *     secondary_phone?: string|null,
     *     address_line1: string,
     *     address_line2?: string|null,
     *     city_name?: string|null,
     *     district_name?: string|null,
     *     postal_code?: string|null,
     *     full_address_text?: string|null,
     *     items: list<array{product_variant_id: int, quantity: int, selected_selling_price?: float|null}>,
     *     discount_amount?: float|string,
     *     courier_id?: int|null,
     *     courier_service_id?: int|null,
     *     courier_city_id?: int|null,
     *     courier_fee_amount?: float|string|null,
     *     duplicate_warning_overridden?: bool,
     *     after_hours_warning_shown?: bool,
     *     intent?: string,
     *     assignment_target?: string,
     *     assign_cca_id?: int|null
     * }  $input
     *
     * courier_fee_amount is applied only when no courier is selected (import / fee-only drafts).
     * When a courier is selected, the authoritative fee preview still wins.
     */

    public function create(User $actor, array $input): Order
    {
        if ($actor->company_id === null) {
            throw ValidationException::withMessages([
                'reseller_company_id' => ['Authenticated user has no company context.'],
            ]);
        }
        $actor->loadMissing('company.owner');
        $isCca = $this->ccaEligibilityService->isEligible($actor, (int) $actor->company_id);
        $commercialReseller = $this->resolveCommercialReseller($actor, $isCca);
        $market = Market::query()->with('country')->findOrFail((int) $input['market_id']);
        $countryId = (int) $market->country_id;
        $items = $this->resolveAuthoritativeItems(
            $this->mergeVariantQuantities($input['items'] ?? [])
        );
        $customerName = trim((string) $input['customer_name']);
        $secondaryPhone = isset($input['secondary_phone']) ? trim((string) $input['secondary_phone']) : null;
        if ($secondaryPhone === '') {
            $secondaryPhone = null;
        }
        $intent = (string) ($input['intent'] ?? self::INTENT_SEND_TO_CALL_CENTER);
        if (! in_array($intent, [self::INTENT_CONFIRM, self::INTENT_SEND_TO_CALL_CENTER], true)) {
            throw ValidationException::withMessages([
                'intent' => ['Invalid order creation intent.'],
            ]);
        }
        $addressLine1 = trim((string) $input['address_line1']);
        $cityName = filled($input['city_name'] ?? null) ? trim((string) $input['city_name']) : '';
        $districtName = filled($input['district_name'] ?? null) ? trim((string) $input['district_name']) : null;
        $draft = $this->resolveDraftCourier(
            supplierId: (int) $input['supplier_id'],
            marketId: (int) $market->id,
            courierId: isset($input['courier_id']) ? (int) $input['courier_id'] : null,
            courierServiceId: isset($input['courier_service_id']) ? (int) $input['courier_service_id'] : null,
            courierCityId: isset($input['courier_city_id']) ? (int) $input['courier_city_id'] : null,
            districtName: $districtName,
            cityName: $cityName !== '' ? $cityName : null,
        );
        if ($draft['city_name'] !== null && $cityName === '') {
            $cityName = $draft['city_name'];
        }
        if ($draft['district_name'] !== null && $districtName === null) {
            $districtName = $draft['district_name'];
        }
        $discountAmount = round((float) ($input['discount_amount'] ?? 0), 2);
        $itemsSubtotal = round(array_sum(array_map(
            static fn (array $line) => round($line['unit_selling_price'] * $line['quantity'], 2),
            $items,
        )), 2);
        $totalWeight = $this->estimateItemsWeight($items);
        $courierFeeAmount = 0.0;
        if ($draft['draft_courier_id'] !== null) {
            $feePreview = $this->courierLookupService->feePreviewForSupplierMarket(
                (int) $input['supplier_id'],
                (int) $market->id,
                (int) $draft['draft_courier_id'],
                $totalWeight,
                $itemsSubtotal,
                $discountAmount,
            );
            $courierFeeAmount = (float) $feePreview['courier_fee_amount'];
        } elseif (array_key_exists('courier_fee_amount', $input) && $input['courier_fee_amount'] !== null) {
            $courierFeeAmount = round((float) $input['courier_fee_amount'], 2);
            if ($courierFeeAmount < 0) {
                throw ValidationException::withMessages([
                    'courier_fee_amount' => ['Courier fee cannot be negative.'],
                ]);
            }
        }
        $payload = [
            'source' => OrderSource::MANUAL,
            'order_type' => OrderType::NEW,
            'market_id' => (int) $market->id,
            'reseller_id' => (int) $commercialReseller->id,
            'reseller_company_id' => (int) $actor->company_id,
            'supplier_id' => (int) $input['supplier_id'],
            'created_by' => (int) $actor->id,
            'available_in_pool' => false,
            'draft_courier_id' => $draft['draft_courier_id'],
            'draft_courier_service_id' => $draft['draft_courier_service_id'],
            'draft_courier_city_id' => $draft['draft_courier_city_id'],
            'customer' => [
                'display_name' => $customerName,
                'primary_country_id' => $countryId,
                'primary_phone' => trim((string) $input['primary_phone']),
                'primary_phone_country_id' => $countryId,
                'secondary_phone' => $secondaryPhone,
                'secondary_phone_country_id' => $secondaryPhone !== null ? $countryId : null,
            ],
            'address' => [
                'recipient_name' => $customerName,
                'line1' => $addressLine1,
                'line2' => filled($input['address_line2'] ?? null) ? trim((string) $input['address_line2']) : null,
                'city_name' => $cityName,
                'district_name' => $districtName,
                'postal_code' => filled($input['postal_code'] ?? null) ? trim((string) $input['postal_code']) : null,
                'country_id' => $countryId,
                'full_address_text' => filled($input['full_address_text'] ?? null)
                    ? trim((string) $input['full_address_text'])
                    : $addressLine1,
            ],
            'items' => $items,
            'discount_amount' => $discountAmount,
            'courier_fee_amount' => $courierFeeAmount,
            'duplicate_warning_overridden' => (bool) ($input['duplicate_warning_overridden'] ?? false),
            'after_hours_warning_shown' => (bool) ($input['after_hours_warning_shown'] ?? false),
        ];
        try {
            $order = $this->orderService->create($payload);
        } catch (ConflictingCustomerIdentityException $e) {
            throw ValidationException::withMessages([
                'primary_phone' => [
                    'The primary and secondary phone numbers belong to different customers. '
                    .'Order creation was rejected. Do not merge customers silently.',
                ],
                'secondary_phone' => [
                    'Conflicting customer identities detected (customers '
                    .$e->primaryCustomerId.' and '.$e->secondaryCustomerId.').',
                ],
            ]);
        }
        if ($isCca) {
            $order = $this->ccaAssignmentService->assign(
                $order,
                (int) $actor->id,
                (int) $actor->id,
                null,
                (int) $actor->company_id,
                OrderCcaAssignmentOrigin::MANUAL_CREATE,
            );
        } elseif ($intent === self::INTENT_SEND_TO_CALL_CENTER) {
            $order = $this->applyResellerSendAssignment($actor, $order, $input);
        }
        // INTENT_CONFIRM intentionally stays PENDING. Courier booking / CONFIRMED
        // is a separate future workflow.
        return $order->fresh([
            'items',
            'cca',
            'ccaAssignments',
            'statusHistories',
            'address',
            'shipment',
            'draftCourier',
            'draftCourierService',
            'draftCourierCity',
        ]);
    }

    /**
     * Update commercial fields on an existing company order.
     *
     * @param  array{
     *     market_id: int,
     *     supplier_id: int,
     *     customer_name: string,
     *     primary_phone: string,
     *     secondary_phone?: string|null,
     *     address_line1: string,
     *     address_line2?: string|null,
     *     city_name?: string|null,
     *     district_name?: string|null,
     *     postal_code?: string|null,
     *     full_address_text?: string|null,
     *     items: list<array{product_variant_id: int, quantity: int, selected_selling_price?: float|null}>,
     *     discount_amount?: float|string,
     *     courier_id?: int|null,
     *     courier_service_id?: int|null,
     *     courier_city_id?: int|null,
     *     duplicate_warning_overridden?: bool,
     *     after_hours_warning_shown?: bool
     * }  $input
     */
    public function update(User $actor, Order $order, array $input): Order
    {
        if ($actor->company_id === null) {
            throw ValidationException::withMessages([
                'reseller_company_id' => ['Authenticated user has no company context.'],
            ]);
        }

        if ((int) $order->reseller_company_id !== (int) $actor->company_id) {
            abort(404);
        }

        if ((int) $input['market_id'] !== (int) $order->market_id) {
            throw ValidationException::withMessages([
                'market_id' => ['Market cannot be changed on an existing order.'],
            ]);
        }

        if ((int) $input['supplier_id'] !== (int) $order->supplier_id) {
            throw ValidationException::withMessages([
                'supplier_id' => ['Supplier cannot be changed on an existing order.'],
            ]);
        }

        $actor->loadMissing('company.owner');
        $isCca = $this->ccaEligibilityService->isEligible($actor, (int) $actor->company_id);
        $market = Market::query()->with('country')->findOrFail((int) $order->market_id);
        $countryId = (int) $market->country_id;
        $items = $this->resolveAuthoritativeItems(
            $this->mergeVariantQuantities($input['items'] ?? [])
        );
        $customerName = trim((string) $input['customer_name']);
        $secondaryPhone = isset($input['secondary_phone']) ? trim((string) $input['secondary_phone']) : null;
        if ($secondaryPhone === '') {
            $secondaryPhone = null;
        }

        $addressLine1 = trim((string) $input['address_line1']);
        $cityName = filled($input['city_name'] ?? null) ? trim((string) $input['city_name']) : '';
        $districtName = filled($input['district_name'] ?? null) ? trim((string) $input['district_name']) : null;
        $draft = $this->resolveDraftCourier(
            supplierId: (int) $order->supplier_id,
            marketId: (int) $market->id,
            courierId: isset($input['courier_id']) ? (int) $input['courier_id'] : null,
            courierServiceId: isset($input['courier_service_id']) ? (int) $input['courier_service_id'] : null,
            courierCityId: isset($input['courier_city_id']) ? (int) $input['courier_city_id'] : null,
            districtName: $districtName,
            cityName: $cityName !== '' ? $cityName : null,
        );
        if ($draft['city_name'] !== null && $cityName === '') {
            $cityName = $draft['city_name'];
        }
        if ($draft['district_name'] !== null && $districtName === null) {
            $districtName = $draft['district_name'];
        }

        // CCAs cannot change discounts; keep the persisted value.
        $discountAmount = $isCca
            ? round((float) $order->discount_amount, 2)
            : round((float) ($input['discount_amount'] ?? $order->discount_amount), 2);

        $itemsSubtotal = round(array_sum(array_map(
            static fn (array $line) => round($line['unit_selling_price'] * $line['quantity'], 2),
            $items,
        )), 2);
        $totalWeight = $this->estimateItemsWeight($items);
        $courierFeeAmount = 0.0;
        if ($draft['draft_courier_id'] !== null) {
            $feePreview = $this->courierLookupService->feePreviewForSupplierMarket(
                (int) $order->supplier_id,
                (int) $market->id,
                (int) $draft['draft_courier_id'],
                $totalWeight,
                $itemsSubtotal,
                $discountAmount,
            );
            $courierFeeAmount = (float) $feePreview['courier_fee_amount'];
        }

        $payload = [
            'customer' => [
                'display_name' => $customerName,
                'primary_country_id' => $countryId,
                'primary_phone' => trim((string) $input['primary_phone']),
                'primary_phone_country_id' => $countryId,
                'secondary_phone' => $secondaryPhone,
                'secondary_phone_country_id' => $secondaryPhone !== null ? $countryId : null,
            ],
            'address' => [
                'recipient_name' => $customerName,
                'line1' => $addressLine1,
                'line2' => filled($input['address_line2'] ?? null) ? trim((string) $input['address_line2']) : null,
                'city_name' => $cityName,
                'district_name' => $districtName,
                'postal_code' => filled($input['postal_code'] ?? null) ? trim((string) $input['postal_code']) : null,
                'country_id' => $countryId,
                'full_address_text' => filled($input['full_address_text'] ?? null)
                    ? trim((string) $input['full_address_text'])
                    : $addressLine1,
            ],
            'items' => $items,
            'discount_amount' => $discountAmount,
            'courier_fee_amount' => $courierFeeAmount,
            'draft_courier_id' => $draft['draft_courier_id'],
            'draft_courier_service_id' => $draft['draft_courier_service_id'],
            'draft_courier_city_id' => $draft['draft_courier_city_id'],
            'duplicate_warning_overridden' => (bool) ($input['duplicate_warning_overridden'] ?? false),
            'after_hours_warning_shown' => (bool) ($input['after_hours_warning_shown'] ?? false),
            'updated_by' => (int) $actor->id,
        ];

        try {
            $order = $this->orderService->updateDetails(
                $order,
                $payload,
                (int) $actor->id,
                (int) $actor->company_id,
            );
        } catch (ConflictingCustomerIdentityException $e) {
            throw ValidationException::withMessages([
                'primary_phone' => [
                    'The primary and secondary phone numbers belong to different customers. '
                    .'Order update was rejected. Do not merge customers silently.',
                ],
                'secondary_phone' => [
                    'Conflicting customer identities detected (customers '
                    .$e->primaryCustomerId.' and '.$e->secondaryCustomerId.').',
                ],
            ]);
        }

        return $order->fresh([
            'items.variant.product',
            'cca',
            'ccaAssignments',
            'statusHistories',
            'address',
            'shipment',
            'draftCourier',
            'draftCourierService',
            'draftCourierCity',
        ]);
    }

    /**
     * Serialize duplicate warning payload for the create form flash/session.
     *
     * @return list<array{
     *     id: int,
     *     uuid: string,
     *     order_number: ?string,
     *     status: string,
     *     created_at: ?string,
     *     items: list<array{product: string, variant: string, quantity: int}>
     * }>
     */

    public function serializeDuplicates(DuplicateOrderWarningException $exception): array
    {
        return Collection::make($exception->duplicates)
            ->map(function (Order $order) {
                $order->loadMissing('items');
                return [
                    'id' => (int) $order->id,
                    'uuid' => (string) $order->uuid,
                    'order_number' => $order->order_number,
                    'status' => $order->status instanceof \BackedEnum
                        ? $order->status->value
                        : (string) $order->status,
                    'created_at' => optional($order->created_at)?->toDateTimeString(),
                    'items' => $order->items->map(fn ($item) => [
                        'product' => (string) $item->product_name_snapshot,
                        'variant' => (string) $item->variant_name_snapshot,
                        'quantity' => (int) $item->quantity,
                    ])->values()->all(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $input
     */

    private function applyResellerSendAssignment(User $actor, Order $order, array $input): Order
    {
        $target = (string) ($input['assignment_target'] ?? self::ASSIGNMENT_UNASSIGNED);
        return match ($target) {
            self::ASSIGNMENT_UNASSIGNED => $this->ccaAssignmentService->unassign(
                $order,
                (int) $actor->id,
                (int) $actor->company_id,
            ),
            self::ASSIGNMENT_POOL => $this->ccaAssignmentService->moveToPool(
                $order,
                (int) $actor->id,
                (int) $actor->company_id,
            ),
            self::ASSIGNMENT_CCA => $this->ccaAssignmentService->assign(
                $order,
                (int) ($input['assign_cca_id'] ?? 0),
                (int) $actor->id,
                null,
                (int) $actor->company_id,
                OrderCcaAssignmentOrigin::DIRECT,
            ),
            default => throw ValidationException::withMessages([
                'assignment_target' => ['Invalid call-center assignment target.'],
            ]),
        };
    }

    private function resolveCommercialReseller(User $actor, bool $isCca): User
    {
        if (! $isCca) {
            return $actor;
        }
        $owner = $actor->company?->owner;
        if ($owner === null) {
            throw ValidationException::withMessages([
                'reseller_id' => ['Unable to resolve the reseller owner for this company.'],
            ]);
        }
        return $owner;
    }

    /**
     * Merge duplicate variant lines before core uniqueness enforcement.
     *
     * @param  list<array{product_variant_id: int, quantity: int, selected_selling_price?: float|null}>  $items
     * @return list<array{product_variant_id: int, quantity: int, selected_selling_price?: float|null}>
     */

    private function mergeVariantQuantities(array $items): array
    {
        $merged = [];
        foreach ($items as $item) {
            $variantId = (int) ($item['product_variant_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($variantId < 1) {
                continue;
            }

            $selected = array_key_exists('selected_selling_price', $item)
                && $item['selected_selling_price'] !== null
                && $item['selected_selling_price'] !== ''
                ? round((float) $item['selected_selling_price'], 2)
                : null;

            if (! isset($merged[$variantId])) {
                $merged[$variantId] = [
                    'product_variant_id' => $variantId,
                    'quantity' => 0,
                    'selected_selling_price' => $selected,
                ];
            } else {
                $existingSelected = $merged[$variantId]['selected_selling_price'];
                if (
                    $existingSelected !== null
                    && $selected !== null
                    && abs((float) $existingSelected - (float) $selected) > 0.001
                ) {
                    throw ValidationException::withMessages([
                        'items' => ['Duplicate variant lines must use the same selected selling price.'],
                    ]);
                }

                if ($existingSelected === null && $selected !== null) {
                    $merged[$variantId]['selected_selling_price'] = $selected;
                }
            }

            $merged[$variantId]['quantity'] += $quantity;
        }

        return array_values($merged);
    }

    /**
     * @param  list<array{product_variant_id: int, quantity: int, selected_selling_price?: float|null}>  $items
     * @return list<array{product_variant_id: int, quantity: int, unit_selling_price: float}>
     */

    private function resolveAuthoritativeItems(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => ['At least one order item is required.'],
            ]);
        }
        $variantIds = array_map(static fn (array $item) => (int) $item['product_variant_id'], $items);
        $variants = ProductVariant::query()
            ->with('product')
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');
        $resolved = [];
        foreach ($items as $index => $item) {
            $variantId = (int) ($item['product_variant_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);
            $variant = $variants->get($variantId);
            if ($variant === null || $variant->product === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_variant_id" => ['The selected product variant is invalid.'],
                ]);
            }

            $priceLocked = (bool) $variant->product->price_locked;
            $selected = array_key_exists('selected_selling_price', $item)
                && $item['selected_selling_price'] !== null
                && $item['selected_selling_price'] !== ''
                ? (float) $item['selected_selling_price']
                : null;

            try {
                $unitSellingPrice = ResellerProductPricing::customerUnitPrice(
                    $variant,
                    $priceLocked,
                    $selected,
                );
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    "items.{$index}.selected_selling_price" => [
                        $this->selectedSellingPriceErrorMessage($e->getMessage()),
                    ],
                ]);
            }

            $resolved[] = [
                'product_variant_id' => (int) $variant->id,
                'quantity' => $quantity,
                'unit_selling_price' => $unitSellingPrice,
                'unit_weight' => round((float) ($variant->weight ?? 0), 3),
            ];
        }
        return $resolved;
    }

    private function selectedSellingPriceErrorMessage(string $code): string
    {
        return match ($code) {
            ResellerProductPricing::ERROR_SELECTED_PRICE_REQUIRED =>
                'A selling price is required for unlocked products.',
            ResellerProductPricing::ERROR_SUGGESTED_RANGE_UNAVAILABLE =>
                'This product variant has no valid selling price range.',
            ResellerProductPricing::ERROR_SELECTED_PRICE_OUT_OF_RANGE =>
                'The selected selling price must be between the suggested minimum and maximum.',
            default => 'The selected selling price is invalid.',
        };
    }

    /**
     * @param  list<array{product_variant_id: int, quantity: int, unit_selling_price: float, unit_weight?: float}>  $items
     */

    private function estimateItemsWeight(array $items): float
    {
        $total = 0.0;
        foreach ($items as $item) {
            $unitWeight = (float) ($item['unit_weight'] ?? 0);
            $total += round($unitWeight * (int) $item['quantity'], 3);
        }
        return round($total, 3);
    }

    /**
     * @return array{
     *     draft_courier_id: ?int,
     *     draft_courier_service_id: ?int,
     *     draft_courier_city_id: ?int,
     *     district_name: ?string,
     *     city_name: ?string
     * }
     */

    private function resolveDraftCourier(
        int $supplierId,
        int $marketId,
        ?int $courierId,
        ?int $courierServiceId,
        ?int $courierCityId,
        ?string $districtName,
        ?string $cityName,
    ): array {
        if ($courierId === null || $courierId < 1) {
            return [
                'draft_courier_id' => null,
                'draft_courier_service_id' => null,
                'draft_courier_city_id' => null,
                'district_name' => $districtName,
                'city_name' => $cityName,
            ];
        }
        $this->courierLookupService->requireEligibleCourierForSupplierMarket(
            $supplierId,
            $marketId,
            $courierId,
        );
        if ($courierServiceId !== null && $courierServiceId > 0) {
            $service = $this->courierLookupService->requireServiceForCourier($courierId, $courierServiceId);
            $courierServiceId = (int) $service->id;
        } else {
            $defaultService = $this->courierLookupService->firstActiveServiceForCourier($courierId);
            $courierServiceId = $defaultService !== null ? (int) $defaultService->id : null;
        }
        if ($courierCityId !== null && $courierCityId > 0) {
            $city = $this->courierLookupService->requireCityForCourier($courierId, $courierCityId);
            $courierCityId = (int) $city->id;
            $districtName = (string) $city->district_name;
            $cityName = (string) $city->city_name;
        } elseif ($districtName !== null && $cityName !== null) {
            $city = $this->courierLookupService->findCityForCourierDistrictAndName(
                $courierId,
                $districtName,
                $cityName,
            );
            if ($city === null) {
                throw ValidationException::withMessages([
                    'city_name' => ['The selected city is invalid for the chosen courier and district.'],
                ]);
            }
            $courierCityId = (int) $city->id;
            $districtName = (string) $city->district_name;
            $cityName = (string) $city->city_name;
        } else {
            $courierCityId = null;
        }
        return [
            'draft_courier_id' => $courierId,
            'draft_courier_service_id' => $courierServiceId,
            'draft_courier_city_id' => $courierCityId,
            'district_name' => $districtName,
            'city_name' => $cityName,
        ];
    }

    /**
     * Defaults for the shared create/edit form when opening an existing order.
     *
     * @return array<string, mixed>
     */
    public function editFormDefaults(Order $order): array
    {
        $order->loadMissing(['address', 'items.variant.product', 'draftCourierCity']);

        $line1 = (string) ($order->address?->line1 ?? '');
        $line2 = (string) ($order->address?->line2 ?? '');
        $addressDisplay = trim($line1.($line2 !== '' ? ', '.$line2 : ''));

        return [
            'market_id' => (int) $order->market_id,
            'supplier_id' => (int) $order->supplier_id,
            'customer_name' => (string) $order->customer_name_snapshot,
            'primary_phone' => (string) $order->primary_phone_snapshot,
            'secondary_phone' => (string) ($order->secondary_phone_snapshot ?? ''),
            'address_line1' => $addressDisplay !== '' ? $addressDisplay : $line1,
            'address_line2' => '',
            'city_name' => (string) ($order->address?->city_name ?? ''),
            'district_name' => (string) ($order->address?->district_name ?? ''),
            'postal_code' => (string) ($order->address?->postal_code ?? ''),
            'full_address_text' => (string) ($order->address?->full_address_text ?? $line1),
            'courier_id' => $order->draft_courier_id !== null ? (int) $order->draft_courier_id : null,
            'courier_service_id' => $order->draft_courier_service_id !== null
                ? (int) $order->draft_courier_service_id
                : null,
            'courier_city_id' => $order->draft_courier_city_id !== null
                ? (int) $order->draft_courier_city_id
                : null,
            'discount_amount' => round((float) $order->discount_amount, 2),
        ];
    }

    /**
     * @return list<array{
     *     product_id: ?int,
     *     product_name: string,
     *     product_variant_id: int,
     *     quantity: int,
     *     selected_selling_price: float
     * }>
     */
    public function editItemsBootstrap(Order $order): array
    {
        $order->loadMissing(['items.variant.product']);

        return $order->items->map(function ($item) {
            $product = $item->variant?->product;

            return [
                'product_id' => $item->product_id !== null ? (int) $item->product_id : null,
                'product_name' => (string) ($item->product_name_snapshot ?: ($product?->name ?? '')),
                'product_variant_id' => (int) $item->product_variant_id,
                'quantity' => (int) $item->quantity,
                'selected_selling_price' => round((float) $item->unit_selling_price, 2),
            ];
        })->values()->all();
    }
}
