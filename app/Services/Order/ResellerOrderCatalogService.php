<?php

namespace App\Services\Order;

use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Exceptions\ConflictingCustomerIdentityException;
use Feeder\Core\Models\Country;
use Feeder\Core\Models\Customer;
use Feeder\Core\Models\Market;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\ProductVariant;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Order\AfterHoursDeterminationService;
use Feeder\Core\Services\Order\CallCenterAgentEligibilityService;
use Feeder\Core\Services\Order\CribLookupService;
use Feeder\Core\Services\Order\CustomerBanService;
use Feeder\Core\Services\Order\CustomerIdentityService;
use Feeder\Core\Services\Order\OrderCourierLookupService;
use Feeder\Core\Services\Order\OrderService;
use Feeder\Core\Services\ResellerMarketAccessService;
use Feeder\Core\Services\ResellerSupplierAssignmentService;
use Feeder\Core\Support\CurrencyDisplay;
use Feeder\Core\Support\CustomerPhoneNormalizer;
use Feeder\Core\Support\ResellerProductPricing;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Read-side catalog helpers for the Reseller Portal manual order create UI.
 *
 * Authorization boundaries (market / supplier / product visibility) are enforced here
 * for UI options. OrderService remains authoritative on write.
 */
class ResellerOrderCatalogService
{
    public function __construct(
        private readonly ResellerMarketAccessService $marketAccessService,
        private readonly ResellerSupplierAssignmentService $supplierAssignmentService,
        private readonly AfterHoursDeterminationService $afterHoursDeterminationService,
        private readonly CustomerBanService $customerBanService,
        private readonly CustomerIdentityService $customerIdentityService,
        private readonly CustomerPhoneNormalizer $phoneNormalizer,
        private readonly CribLookupService $cribLookupService,
        private readonly CallCenterAgentEligibilityService $ccaEligibilityService,
        private readonly OrderService $orderService,
        private readonly OrderCourierLookupService $courierLookupService,
    ) {}

    /**
     * @return Collection<int, array{id: int, code: string, name: string, country_id: int, currency_code: string}>
     */
    public function marketsForReseller(User $actor): Collection
    {
        $company = $actor->company;

        if ($company === null) {
            return collect();
        }

        return $company->allowedMarkets()
            ->with(['country', 'currency'])
            ->where('markets.is_active', true)
            ->orderBy('markets.name')
            ->get()
            ->map(fn (Market $market) => [
                'id' => (int) $market->id,
                'code' => (string) $market->code,
                'name' => (string) $market->name,
                'country_id' => (int) $market->country_id,
                'currency_code' => CurrencyDisplay::inputLabel($market->currency),
            ])
            ->values();
    }

    /**
     * Assigned suppliers that have sellable products in the selected market.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    public function suppliersForMarket(User $actor, int $marketId): Collection
    {
        $reseller = $this->resolveCommercialReseller($actor);
        $this->assertMarketAccess($actor, $marketId);

        $assignedIds = $this->supplierAssignmentService
            ->assignedSupplierIds($reseller)
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($assignedIds === []) {
            return collect();
        }

        $supplierIdsWithProducts = Product::query()
            ->whereIn('supplier_id', $assignedIds)
            ->where('market_id', $marketId)
            ->where('status', ProductStatus::ACTIVE)
            ->where('system_visible', true)
            ->distinct()
            ->pluck('supplier_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($supplierIdsWithProducts === []) {
            return collect();
        }

        return User::query()
            ->whereIn('id', $supplierIdsWithProducts)
            ->with('company')
            ->get()
            ->map(fn (User $supplier) => [
                'id' => (int) $supplier->id,
                'name' => $supplier->company?->name ?? 'Supplier #'.$supplier->id,
            ])
            ->sortBy(fn (array $row) => mb_strtolower($row['name']))
            ->values();
    }

    /**
     * @return Collection<int, array{id: int, name: string, variant_count: int}>
     */
    public function productsForSupplier(
        User $actor,
        int $marketId,
        int $supplierId,
        ?string $search = null,
    ): Collection {
        $this->assertMarketAccess($actor, $marketId);
        $this->assertSupplierAssigned($actor, $supplierId);

        $query = Product::query()
            ->where('supplier_id', $supplierId)
            ->where('market_id', $marketId)
            ->where('status', ProductStatus::ACTIVE)
            ->where('system_visible', true)
            ->withCount(['variants' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name');

        $search = trim((string) $search);

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('name', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%');
            });
        }

        return $query
            ->limit(50)
            ->get()
            ->map(fn (Product $product) => [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'variant_count' => (int) $product->variants_count,
            ])
            ->values();
    }

    /**
     * Zero-stock variants remain visible (explicit business requirement).
     *
     * @return Collection<int, array{
     *     id: int,
     *     name: string,
     *     barcode: ?string,
     *     price_locked: bool,
     *     cost: string,
     *     company_commission: string,
     *     selling_price: string|null,
     *     suggested_price_min: string|null,
     *     suggested_price_max: string|null,
     *     customer_unit_price: string|null,
     *     weight: string
     * }>
     */
    public function variantsForProduct(
        User $actor,
        int $marketId,
        int $supplierId,
        int $productId,
    ): Collection {
        $this->assertMarketAccess($actor, $marketId);
        $this->assertSupplierAssigned($actor, $supplierId);

        $product = Product::query()
            ->whereKey($productId)
            ->where('supplier_id', $supplierId)
            ->where('market_id', $marketId)
            ->where('status', ProductStatus::ACTIVE)
            ->where('system_visible', true)
            ->first();

        if ($product === null) {
            throw ValidationException::withMessages([
                'product_id' => ['The selected product is not available for this market and supplier.'],
            ]);
        }

        $priceLocked = (bool) $product->price_locked;

        return ProductVariant::query()
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (ProductVariant $variant) use ($priceLocked) {
                $defaultCustomerPrice = ResellerProductPricing::defaultCustomerUnitPrice($variant, $priceLocked);

                return [
                    'id' => (int) $variant->id,
                    'name' => (string) $variant->name,
                    'barcode' => $variant->barcode,
                    'price_locked' => $priceLocked,
                    'cost' => number_format((float) $variant->cost, 2, '.', ''),
                    'company_commission' => number_format((float) $variant->company_commission, 2, '.', ''),
                    'selling_price' => $priceLocked && $variant->selling_price !== null
                        ? number_format((float) $variant->selling_price, 2, '.', '')
                        : null,
                    'suggested_price_min' => ! $priceLocked && $variant->suggested_price_min !== null
                        ? number_format((float) $variant->suggested_price_min, 2, '.', '')
                        : null,
                    'suggested_price_max' => ! $priceLocked && $variant->suggested_price_max !== null
                        ? number_format((float) $variant->suggested_price_max, 2, '.', '')
                        : null,
                    'customer_unit_price' => $defaultCustomerPrice !== null
                        ? number_format($defaultCustomerPrice, 2, '.', '')
                        : null,
                    'weight' => number_format((float) ($variant->weight ?? 0), 3, '.', ''),
                ];
            })
            ->values();
    }

    /**
     * @return array{after_hours: bool, after_hours_penalty_amount: float, timezone: string, local_time: string, currency_code: string}
     */
    public function afterHoursForMarket(User $actor, int $marketId): array
    {
        $this->assertMarketAccess($actor, $marketId);

        $market = Market::query()->with('currency')->findOrFail($marketId);
        $result = $this->afterHoursDeterminationService->determine($market);

        return [
            ...$result,
            'currency_code' => CurrencyDisplay::inputLabel($market->currency),
        ];
    }

    /**
     * Phone identity / ban / history / CRIB lookup for UI feedback.
     * Authoritative identity resolution still occurs inside OrderService on create.
     *
     * @return array<string, mixed>
     */
    public function lookupCustomerByPhone(
        User $actor,
        string $rawPhone,
        int $countryId,
        ?int $marketId = null,
        ?string $secondaryPhone = null,
    ): array {
        unset($actor);

        $banResult = $this->customerBanService->findBanByRawPhone($rawPhone, $countryId);
        $customer = $banResult['customer'];

        $phoneConflict = null;
        if ($secondaryPhone !== null && trim($secondaryPhone) !== '') {
            try {
                $this->assertNoPhoneConflict($rawPhone, $secondaryPhone, $countryId);
            } catch (ConflictingCustomerIdentityException $e) {
                $phoneConflict = [
                    'primary_customer_id' => $e->primaryCustomerId,
                    'secondary_customer_id' => $e->secondaryCustomerId,
                    'message' => 'Primary and secondary phones belong to different customers.',
                ];
            }
        }

        $history = [];
        if ($customer !== null) {
            $history = $this->serializeCustomerOrderHistory($customer);
        }

        $crib = null;
        if ($marketId !== null && $banResult['phone'] !== null) {
            $record = $this->cribLookupService->findByPhone(
                $marketId,
                (string) $banResult['phone']->normalized_phone,
            );
            if ($record !== null) {
                $crib = [
                    'risk_level' => $record->risk_level,
                    'risk_code' => $record->risk_code,
                    'risk_summary' => $record->risk_summary,
                ];
            }
        }

        return [
            'found' => $banResult['found'],
            'is_banned' => $banResult['is_banned'],
            'customer_id' => $customer?->id,
            'display_name' => $customer?->display_name,
            'ban_reason' => $banResult['active_ban']?->reason,
            'phone_conflict' => $phoneConflict,
            'crib' => $crib,
            'order_history' => $history,
        ];
    }

    /**
     * Preview duplicate detection using authoritative domain finder.
     *
     * @param  list<int>  $variantIds
     * @return list<array<string, mixed>>
     */
    public function previewDuplicates(User $actor, string $rawPhone, int $countryId, array $variantIds): array
    {
        unset($actor);

        $country = Country::query()->find($countryId);
        if ($country === null) {
            throw ValidationException::withMessages([
                'country_id' => ['The selected country is invalid.'],
            ]);
        }

        $normalized = $this->phoneNormalizer->normalize($rawPhone, $country);
        $phone = $this->customerIdentityService->findByCountryAndNormalizedPhone(
            (int) $country->id,
            $normalized,
        );

        if ($phone === null) {
            return [];
        }

        $duplicates = $this->orderService->findPotentialDuplicates(
            (int) $phone->customer_id,
            array_values(array_unique(array_map('intval', $variantIds))),
        );

        return Collection::make($duplicates)
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
     * Eligible CCAs for assignment UI on manual create / bulk assign.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    public function eligibleCcasForCompany(User $actor): Collection
    {
        return User::query()
            ->where('company_id', (int) $actor->company_id)
            ->whereHas('role', fn ($query) => $query->where(
                'slug',
                CallCenterAgentEligibilityService::ROLE_SLUG
            ))
            ->with(['profile', 'role.portal'])
            ->orderBy('id')
            ->get()
            ->filter(fn (User $cca) => $this->ccaEligibilityService->isEligible(
                $cca,
                (int) $actor->company_id
            ))
            ->map(function (User $cca) {
                $name = trim(($cca->profile?->first_name ?? '').' '.($cca->profile?->last_name ?? ''));

                return [
                    'id' => (int) $cca->id,
                    'name' => $name !== '' ? $name : ($cca->phone ?? 'CCA #'.$cca->id),
                ];
            })
            ->values();
    }

    private function assertNoPhoneConflict(string $primaryPhone, string $secondaryPhone, int $countryId): void
    {
        $country = Country::query()->findOrFail($countryId);
        $primaryNormalized = $this->phoneNormalizer->normalize($primaryPhone, $country);
        $secondaryNormalized = $this->phoneNormalizer->normalize($secondaryPhone, $country);

        $primary = $this->customerIdentityService->findByCountryAndNormalizedPhone(
            (int) $country->id,
            $primaryNormalized,
        );
        $secondary = $this->customerIdentityService->findByCountryAndNormalizedPhone(
            (int) $country->id,
            $secondaryNormalized,
        );

        if ($primary && $secondary && (int) $primary->customer_id !== (int) $secondary->customer_id) {
            throw new ConflictingCustomerIdentityException(
                (int) $primary->customer_id,
                (int) $secondary->customer_id,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeCustomerOrderHistory(Customer $customer): array
    {
        return Order::query()
            ->where('customer_id', $customer->id)
            ->with([
                'items',
                'resellerCompany',
                'reseller.profile',
                'shipment.courier',
            ])
            ->latest('id')
            ->limit(50)
            ->get()
            ->map(function (Order $order) {
                $resellerName = trim(
                    ($order->reseller?->profile?->first_name ?? '').' '
                    .($order->reseller?->profile?->last_name ?? '')
                );

                return [
                    'order_number' => (string) $order->order_number,
                    'date' => optional($order->created_at)?->format('Y-m-d H:i'),
                    'status' => $order->status instanceof \BackedEnum
                        ? $order->status->label()
                        : (string) $order->status,
                    'amount' => (float) $order->customer_payable_amount,
                    'currency' => (string) $order->currency_code_snapshot,
                    'items' => $order->items
                        ->map(fn ($item) => $item->product_name_snapshot.' × '.$item->quantity)
                        ->implode(', '),
                    'reseller_company' => $order->resellerCompany?->name,
                    'reseller_name' => $resellerName !== '' ? $resellerName : null,
                    'courier_name' => $order->shipment?->courier?->name,
                    'tracking_number' => $order->shipment?->tracking_number,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Eligible local couriers for create-form draft selection (no API / no shipment).
     *
     * @return list<array{id: int, uuid: string, code: string, name: string}>
     */
    public function couriersForCreate(User $actor, int $marketId, int $supplierId): array
    {
        $this->assertMarketAccess($actor, $marketId);
        $this->assertSupplierAssigned($actor, $supplierId);

        return $this->courierLookupService->eligibleCouriersForSupplierMarket($supplierId, $marketId);
    }

    /**
     * @return list<array{district: string}>
     */
    public function courierDistrictsForCreate(User $actor, int $marketId, int $supplierId, int $courierId): array
    {
        $this->assertMarketAccess($actor, $marketId);
        $this->assertSupplierAssigned($actor, $supplierId);
        $this->courierLookupService->requireEligibleCourierForSupplierMarket($supplierId, $marketId, $courierId);

        return $this->courierLookupService->districtsForCourier($courierId);
    }

    /**
     * @return list<array{id: int, uuid: string, city_name: string, district_name: string, external_city_code: string}>
     */
    public function courierCitiesForCreate(
        User $actor,
        int $marketId,
        int $supplierId,
        int $courierId,
        string $district,
    ): array {
        $this->assertMarketAccess($actor, $marketId);
        $this->assertSupplierAssigned($actor, $supplierId);
        $this->courierLookupService->requireEligibleCourierForSupplierMarket($supplierId, $marketId, $courierId);

        return $this->courierLookupService->citiesForCourierAndDistrict($courierId, $district);
    }

    /**
     * @return array{
     *     courier_fee_amount: float,
     *     customer_payable_amount: float,
     *     items_subtotal: float,
     *     discount_amount: float,
     *     total_weight: float
     * }
     */
    public function courierFeePreviewForCreate(
        User $actor,
        int $marketId,
        int $supplierId,
        int $courierId,
        float $weightKg,
        float $itemsSubtotal,
        float $discountAmount,
    ): array {
        $this->assertMarketAccess($actor, $marketId);
        $this->assertSupplierAssigned($actor, $supplierId);

        return $this->courierLookupService->feePreviewForSupplierMarket(
            $supplierId,
            $marketId,
            $courierId,
            $weightKg,
            $itemsSubtotal,
            $discountAmount,
        );
    }

    private function resolveCommercialReseller(User $actor): User
    {
        $actor->loadMissing('company.owner');

        if ($this->ccaEligibilityService->isEligible($actor, (int) $actor->company_id)) {
            return $actor->company?->owner ?? $actor;
        }

        return $actor;
    }

    private function assertMarketAccess(User $actor, int $marketId): void
    {
        $company = $actor->company;

        if ($company === null || ! $this->marketAccessService->hasMarketAccess($company, $marketId)) {
            throw ValidationException::withMessages([
                'market_id' => ['The reseller does not have access to the selected market.'],
            ]);
        }
    }

    private function assertSupplierAssigned(User $actor, int $supplierId): void
    {
        $reseller = $this->resolveCommercialReseller($actor);

        if (! $this->supplierAssignmentService->isSupplierAssigned((int) $reseller->id, $supplierId)) {
            throw ValidationException::withMessages([
                'supplier_id' => ['The selected supplier is not assigned to this reseller.'],
            ]);
        }
    }
}
