<?php

namespace App\Services\Order;

use App\Support\SimpleXlsxWriter;
use Feeder\Core\Enums\OrderImportRowStatus;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Models\Country;
use Feeder\Core\Models\ProductVariant;
use Feeder\Core\Models\ResellerSupplierAssignment;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Order\CustomerBanService;
use Feeder\Core\Services\Order\OrderImportFileParser;
use Feeder\Core\Support\ResellerProductPricing;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use RuntimeException;

/**
 * Development-only generator for reseller bulk order import spreadsheets.
 *
 * Uses real catalog variants and the same validation path as ResellerOrderImportService.
 * Does not create orders, customers, couriers, or mutate production business data.
 */
class TestOrderImportGeneratorService
{
    public const DEFAULT_RESELLER_PHONE = '0799000001';

    /** Reserved SL mobiles for this generator: 0709230001 … (avoids DummyOrderSeeder 070911xxxx). */
    private const PHONE_PREFIX = '070923';

    /** @var list<int> */
    private const DELIVERY_FEES = [300, 350, 400, 450];

    public function __construct(
        private readonly ResellerOrderImportService $importService,
        private readonly CustomerBanService $customerBanService,
        private readonly SimpleXlsxWriter $xlsxWriter,
    ) {}

    /**
     * @return array{
     *     absolute_path: string,
     *     row_count: int,
     *     unique_variants: int,
     *     locked_rows: int,
     *     unlocked_rows: int,
     *     markets: list<string>,
     *     suppliers: list<string>,
     *     reseller_phone: string
     * }
     */
    public function generate(
        int $rows = 20,
        string $outputRelativeOrAbsolute = 'storage/app/test-import-orders.xlsx',
        string $resellerPhone = self::DEFAULT_RESELLER_PHONE,
    ): array {
        if ($rows < 1) {
            throw new InvalidArgumentException('Row count must be at least 1.');
        }

        $actor = $this->resolveReseller($resellerPhone);
        $eligible = $this->resolveEligibleVariants($actor);

        if ($eligible->isEmpty()) {
            throw new RuntimeException(
                'No eligible product variants found for reseller '.$resellerPhone
                .' (active, system-visible, market-accessible, supplier-assigned, with valid pricing).'
            );
        }

        $selected = $this->selectVariantsForRows($eligible, $rows);
        $country = $this->resolvePrimaryCountry($selected);
        $phones = $this->allocateUniquePhones($rows, $country);

        $importMatrix = [OrderImportFileParser::REQUIRED_HEADERS];
        $infoMatrix = [[
            'generated_at',
            'row_number',
            'variant_id',
            'product_name',
            'variant_name',
            'barcode',
            'supplier',
            'market',
            'pricing_mode',
            'generated_selling_price',
            'quantity',
            'delivery',
        ]];

        $generatedAt = now()->toDateTimeString();
        $lockedRows = 0;
        $unlockedRows = 0;
        $marketsUsed = [];
        $suppliersUsed = [];
        $variantIdsUsed = [];

        foreach ($selected as $index => $variant) {
            $rowNumber = $index + 1;
            $product = $variant->product;
            $priceLocked = (bool) $product->price_locked;
            $price = $this->resolveSellingPrice($variant, $priceLocked);
            $qty = ($index % 3) + 1;
            $delivery = self::DELIVERY_FEES[$index % count(self::DELIVERY_FEES)];
            $name = 'Test Customer '.str_pad((string) $rowNumber, 3, '0', STR_PAD_LEFT);
            $address = 'No. '.$rowNumber.', Test Import Avenue, Colombo';
            $phone = $phones[$index];

            $parsedRow = [
                'row_number' => $rowNumber + 1, // spreadsheet data row (header is 1)
                'name' => $name,
                'address' => $address,
                'tp_1' => $phone,
                'tp_2' => '',
                'price' => $this->formatMoney($price),
                'qty' => (string) $qty,
                'item_code' => (string) $variant->id,
                'delivery' => (string) $delivery,
            ];

            $validated = $this->importService->validateParsedRow($actor, $parsedRow);
            if ($validated['status'] !== OrderImportRowStatus::VALID) {
                throw new RuntimeException(
                    'Generated row '.$rowNumber.' failed import validation: '
                    .($validated['validation_errors'] ?? 'unknown error')
                );
            }

            $importMatrix[] = [
                $parsedRow['name'],
                $parsedRow['address'],
                $parsedRow['tp_1'],
                $parsedRow['tp_2'],
                $parsedRow['price'],
                $parsedRow['qty'],
                $parsedRow['item_code'],
                $parsedRow['delivery'],
            ];

            $supplierName = $product->supplier?->company?->name
                ?? ('Supplier #'.$product->supplier_id);
            $marketName = $product->market?->name
                ?? ('Market #'.$product->market_id);
            $pricingMode = $priceLocked ? 'locked' : 'unlocked';

            $infoMatrix[] = [
                $generatedAt,
                (string) $rowNumber,
                (string) $variant->id,
                (string) $product->name,
                (string) $variant->name,
                (string) ($variant->barcode ?? ''),
                $supplierName,
                $marketName,
                $pricingMode,
                $this->formatMoney($price),
                (string) $qty,
                (string) $delivery,
            ];

            if ($priceLocked) {
                $lockedRows++;
            } else {
                $unlockedRows++;
            }

            $marketsUsed[$marketName] = true;
            $suppliersUsed[$supplierName] = true;
            $variantIdsUsed[(int) $variant->id] = true;
        }

        $absolutePath = $this->resolveAbsolutePath($outputRelativeOrAbsolute);
        $this->xlsxWriter->write($absolutePath, [
            'Orders' => $importMatrix,
            'Test Data Info' => $infoMatrix,
        ]);

        return [
            'absolute_path' => $absolutePath,
            'row_count' => $rows,
            'unique_variants' => count($variantIdsUsed),
            'locked_rows' => $lockedRows,
            'unlocked_rows' => $unlockedRows,
            'markets' => array_keys($marketsUsed),
            'suppliers' => array_keys($suppliersUsed),
            'reseller_phone' => $resellerPhone,
        ];
    }

    private function resolveReseller(string $phone): User
    {
        $user = User::query()
            ->where('phone', $phone)
            ->with(['company.portal', 'company.owner'])
            ->first();

        if ($user === null) {
            throw new RuntimeException(
                'Reseller with phone '.$phone.' was not found. Refusing to invent a reseller.'
            );
        }

        if ($user->company === null || ! $user->company->isResellerCompany()) {
            throw new RuntimeException(
                'User '.$phone.' is not attached to a reseller company.'
            );
        }

        $status = $user->status instanceof UserStatus
            ? $user->status
            : UserStatus::tryFrom((string) $user->status);

        if ($status !== UserStatus::ACTIVE) {
            throw new RuntimeException('Reseller '.$phone.' is not ACTIVE.');
        }

        return $user;
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    private function resolveEligibleVariants(User $actor): Collection
    {
        $supplierIds = ResellerSupplierAssignment::query()
            ->where('reseller_id', (int) $actor->id)
            ->pluck('supplier_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($supplierIds === []) {
            return collect();
        }

        $marketIds = $actor->company
            ->allowedMarkets()
            ->where('markets.is_active', true)
            ->pluck('markets.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($marketIds === []) {
            return collect();
        }

        return ProductVariant::query()
            ->where('is_active', true)
            ->whereHas('product', function ($query) use ($supplierIds, $marketIds): void {
                $query->whereIn('supplier_id', $supplierIds)
                    ->whereIn('market_id', $marketIds)
                    ->where('status', ProductStatus::ACTIVE)
                    ->where('system_visible', true);
            })
            ->with([
                'product.market.country',
                'product.supplier.company',
            ])
            ->orderBy('id')
            ->get()
            ->filter(fn (ProductVariant $variant) => $this->hasUsablePricing($variant))
            ->values();
    }

    private function hasUsablePricing(ProductVariant $variant): bool
    {
        $product = $variant->product;
        if ($product === null) {
            return false;
        }

        if ((bool) $product->price_locked) {
            return $variant->selling_price !== null;
        }

        return $variant->suggested_price_min !== null
            && $variant->suggested_price_max !== null
            && (float) $variant->suggested_price_min <= (float) $variant->suggested_price_max;
    }

    /**
     * Prefer one variant per market/supplier combo, then mix locked/unlocked, then reuse.
     *
     * @param  Collection<int, ProductVariant>  $eligible
     * @return list<ProductVariant>
     */
    private function selectVariantsForRows(Collection $eligible, int $rows): array
    {
        $locked = $eligible->filter(fn (ProductVariant $v) => (bool) $v->product->price_locked)->values();
        $unlocked = $eligible->filter(fn (ProductVariant $v) => ! (bool) $v->product->price_locked)->values();

        $byCombo = $eligible->groupBy(
            fn (ProductVariant $v) => $v->product->market_id.'|'.$v->product->supplier_id
        );

        $selected = [];
        $usedIds = [];

        foreach ($byCombo as $group) {
            $pick = $group->first(
                fn (ProductVariant $v) => ! isset($usedIds[(int) $v->id])
            ) ?? $group->first();

            if ($pick !== null) {
                $selected[] = $pick;
                $usedIds[(int) $pick->id] = true;
            }
        }

        foreach ([$locked, $unlocked] as $pool) {
            foreach ($pool as $variant) {
                if (isset($usedIds[(int) $variant->id])) {
                    continue;
                }
                $selected[] = $variant;
                $usedIds[(int) $variant->id] = true;
            }
        }

        if ($selected === []) {
            $selected = $eligible->all();
        }

        $result = [];
        $count = count($selected);
        for ($i = 0; $i < $rows; $i++) {
            $result[] = $selected[$i % $count];
        }

        // When enough distinct variants exist, ensure both pricing modes appear in the first window.
        if ($rows >= 2 && $locked->isNotEmpty() && $unlocked->isNotEmpty()) {
            $result[0] = $locked->first();
            $result[1] = $unlocked->first();
        }

        return $result;
    }

    private function resolveSellingPrice(ProductVariant $variant, bool $priceLocked): float
    {
        if ($priceLocked) {
            return ResellerProductPricing::customerUnitPrice($variant, true, null);
        }

        $min = round((float) $variant->suggested_price_min, 2);
        $max = round((float) $variant->suggested_price_max, 2);
        $mid = round(($min + $max) / 2, 2);

        return ResellerProductPricing::customerUnitPrice($variant, false, $mid);
    }

    /**
     * @param  list<ProductVariant>  $variants
     */
    private function resolvePrimaryCountry(array $variants): Country
    {
        foreach ($variants as $variant) {
            $country = $variant->product?->market?->country;
            if ($country !== null) {
                return $country;
            }
        }

        $fallback = Country::query()->where('iso_code', 'LK')->first();
        if ($fallback === null) {
            throw new RuntimeException('Unable to resolve a country for phone generation.');
        }

        return $fallback;
    }

    /**
     * @return list<string>
     */
    private function allocateUniquePhones(int $count, Country $country): array
    {
        $phones = [];
        $sequence = 1;
        $attempts = 0;
        $maxAttempts = max(500, $count * 50);

        while (count($phones) < $count) {
            $attempts++;
            if ($attempts > $maxAttempts) {
                throw new RuntimeException(
                    'Unable to allocate '.$count.' unique non-banned Sri Lankan test phone numbers.'
                );
            }

            $candidate = self::PHONE_PREFIX.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;

            if (in_array($candidate, $phones, true)) {
                continue;
            }

            $ban = $this->customerBanService->findBanByRawPhone($candidate, (int) $country->id);
            if ($ban['is_banned']) {
                continue;
            }

            $phones[] = $candidate;
        }

        return $phones;
    }

    private function formatMoney(float $amount): string
    {
        $rounded = round($amount, 2);
        if (floor($rounded) == $rounded) {
            return (string) (int) $rounded;
        }

        return number_format($rounded, 2, '.', '');
    }

    private function resolveAbsolutePath(string $outputRelativeOrAbsolute): string
    {
        $path = trim($outputRelativeOrAbsolute);
        if ($path === '') {
            throw new InvalidArgumentException('Output path cannot be empty.');
        }

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path));
    }

    private function isAbsolutePath(string $path): bool
    {
        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z]:[\\\\\\/]/', $path);
    }
}
