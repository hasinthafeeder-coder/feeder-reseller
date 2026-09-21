<?php

namespace App\Services\Order;

use Feeder\Core\Enums\OrderImportBatchStatus;
use Feeder\Core\Enums\OrderImportRowStatus;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Exceptions\ConflictingCustomerIdentityException;
use Feeder\Core\Exceptions\DuplicateOrderWarningException;
use Feeder\Core\Models\Country;
use Feeder\Core\Models\OrderImportBatch;
use Feeder\Core\Models\OrderImportRow;
use Feeder\Core\Models\ProductVariant;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Order\CallCenterAgentEligibilityService;
use Feeder\Core\Services\Order\CustomerBanService;
use Feeder\Core\Services\Order\CustomerIdentityService;
use Feeder\Core\Services\Order\OrderImportFileParser;
use Feeder\Core\Services\ResellerMarketAccessService;
use Feeder\Core\Services\ResellerSupplierAssignmentService;
use Feeder\Core\Services\StockService;
use Feeder\Core\Support\CustomerPhoneNormalizer;
use Feeder\Core\Support\ResellerProductPricing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

/**
 * Reseller Portal adapter for spreadsheet order import (Phase 1.2A).
 *
 * Validation and batch history live here. Successful rows are created through
 * ResellerManualOrderService → OrderService (canonical order creation).
 * CCA assignment for created orders is handled by ResellerOrderAssignmentService
 * (Phase 1.2B) via the Import Orders assignment panel.
 */
class ResellerOrderImportService
{
    public function __construct(
        private readonly OrderImportFileParser $fileParser,
        private readonly ResellerManualOrderService $manualOrderService,
        private readonly CustomerBanService $customerBanService,
        private readonly CustomerIdentityService $customerIdentityService,
        private readonly CustomerPhoneNormalizer $phoneNormalizer,
        private readonly ResellerMarketAccessService $marketAccessService,
        private readonly ResellerSupplierAssignmentService $supplierAssignmentService,
        private readonly CallCenterAgentEligibilityService $ccaEligibilityService,
        private readonly StockService $stockService,
    ) {}

    /**
     * Upload, parse, validate, and persist an import batch with row results.
     *
     * @return array{batch: OrderImportBatch, rows: list<array<string, mixed>>}
     */
    public function uploadAndValidate(User $actor, UploadedFile $file): array
    {
        $this->assertActorContext($actor);
        $commercialReseller = $this->resolveCommercialReseller($actor);

        $parsed = $this->fileParser->parse($file);
        $storedPath = $this->storeUpload($file, (int) $actor->company_id);

        $batch = OrderImportBatch::query()->create([
            'reseller_company_id' => (int) $actor->company_id,
            'reseller_id' => (int) $commercialReseller->id,
            'uploaded_by' => (int) $actor->id,
            'original_filename' => $file->getClientOriginalName(),
            'file_type' => $parsed['file_type'],
            'stored_path' => $storedPath,
            'total_rows' => count($parsed['rows']),
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'banned_rows' => 0,
            'created_rows' => 0,
            'status' => OrderImportBatchStatus::VALIDATED,
        ]);

        $valid = 0;
        $invalid = 0;
        $banned = 0;

        foreach ($parsed['rows'] as $parsedRow) {
            $validated = $this->validateRow($actor, $commercialReseller, $parsedRow);

            OrderImportRow::query()->create([
                'order_import_batch_id' => $batch->id,
                'row_number' => $parsedRow['row_number'],
                'raw_name' => $parsedRow['name'],
                'raw_address' => $parsedRow['address'],
                'raw_tp_1' => $parsedRow['tp_1'],
                'raw_tp_2' => $parsedRow['tp_2'] !== '' ? $parsedRow['tp_2'] : null,
                'raw_price' => $parsedRow['price'],
                'raw_qty' => $parsedRow['qty'],
                'raw_item_code' => $parsedRow['item_code'],
                'raw_delivery' => $parsedRow['delivery'] !== '' ? $parsedRow['delivery'] : null,
                'normalized_tp_1' => $validated['normalized_tp_1'],
                'normalized_tp_2' => $validated['normalized_tp_2'],
                'status' => $validated['status'],
                'validation_errors' => $validated['validation_errors'],
                'product_variant_id' => $validated['product_variant_id'],
                'supplier_id' => $validated['supplier_id'],
                'market_id' => $validated['market_id'],
                'quantity' => $validated['quantity'],
                'unit_selling_price' => $validated['unit_selling_price'],
                'courier_fee_amount' => $validated['courier_fee_amount'],
            ]);

            match ($validated['status']) {
                OrderImportRowStatus::VALID => $valid++,
                OrderImportRowStatus::BANNED => $banned++,
                default => $invalid++,
            };
        }

        $batch->update([
            'valid_rows' => $valid,
            'invalid_rows' => $invalid,
            'banned_rows' => $banned,
        ]);

        return $this->serializeBatch($batch->fresh('rows'));
    }

    /**
     * Create orders for VALID rows. Idempotent for COMPLETED batches.
     *
     * @return array{batch: OrderImportBatch, rows: list<array<string, mixed>>}
     */
    public function process(User $actor, OrderImportBatch $batch): array
    {
        $this->assertActorContext($actor);
        $this->assertBatchOwnership($actor, $batch);

        if ($batch->status === OrderImportBatchStatus::COMPLETED) {
            return $this->serializeBatch($batch->fresh('rows'));
        }

        if ($batch->status === OrderImportBatchStatus::PROCESSING) {
            throw ValidationException::withMessages([
                'batch' => ['This import batch is already being processed.'],
            ]);
        }

        if ($batch->status !== OrderImportBatchStatus::VALIDATED) {
            throw ValidationException::withMessages([
                'batch' => ['This import batch cannot be processed in its current state.'],
            ]);
        }

        $locked = DB::transaction(function () use ($batch): OrderImportBatch {
            $locked = OrderImportBatch::query()
                ->whereKey($batch->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === OrderImportBatchStatus::COMPLETED) {
                return $locked;
            }

            if ($locked->status !== OrderImportBatchStatus::VALIDATED) {
                throw ValidationException::withMessages([
                    'batch' => ['This import batch cannot be processed in its current state.'],
                ]);
            }

            $locked->update([
                'status' => OrderImportBatchStatus::PROCESSING,
                'failure_reason' => null,
            ]);

            return $locked->fresh();
        });

        if ($locked->status === OrderImportBatchStatus::COMPLETED) {
            return $this->serializeBatch($locked->fresh('rows'));
        }

        $created = 0;
        $additionalInvalid = 0;

        try {
            $rows = OrderImportRow::query()
                ->where('order_import_batch_id', $locked->id)
                ->where('status', OrderImportRowStatus::VALID)
                ->orderBy('row_number')
                ->get();

            foreach ($rows as $row) {
                try {
                    $order = DB::transaction(function () use ($actor, $row) {
                        return $this->createOrderForRow($actor, $row);
                    });

                    $row->update([
                        'status' => OrderImportRowStatus::CREATED,
                        'created_order_id' => $order->id,
                        'validation_errors' => null,
                    ]);
                    $created++;
                } catch (ValidationException $e) {
                    $messages = collect($e->errors())->flatten()->filter()->implode(' ');
                    $row->update([
                        'status' => OrderImportRowStatus::INVALID,
                        'validation_errors' => $messages !== ''
                            ? $messages
                            : 'Order creation failed for this row.',
                    ]);
                    $additionalInvalid++;
                }
            }

            $locked->update([
                'status' => OrderImportBatchStatus::COMPLETED,
                'created_rows' => $created,
                'valid_rows' => max(0, (int) $locked->valid_rows - $additionalInvalid),
                'invalid_rows' => (int) $locked->invalid_rows + $additionalInvalid,
                'processed_at' => now(),
            ]);
        } catch (Throwable $e) {
            $locked->update([
                'status' => OrderImportBatchStatus::FAILED,
                'failure_reason' => $e->getMessage(),
                'created_rows' => $created,
            ]);

            throw $e;
        }

        return $this->serializeBatch($batch->fresh('rows'));
    }

    /**
     * @return array{batch: OrderImportBatch, rows: list<array<string, mixed>>}
     */
    public function show(User $actor, OrderImportBatch $batch): array
    {
        $this->assertActorContext($actor);
        $this->assertBatchOwnership($actor, $batch);

        return $this->serializeBatch($batch->fresh('rows'));
    }

    public function templateCsv(): string
    {
        return implode(',', OrderImportFileParser::REQUIRED_HEADERS)."\n";
    }

    /**
     * Validate a single parsed import row using the same rules as uploadAndValidate.
     *
     * Exposed for development test-data generators so validation is not duplicated.
     *
     * @param  array{
     *     row_number: int,
     *     name: string,
     *     address: string,
     *     tp_1: string,
     *     tp_2: string,
     *     price: string,
     *     qty: string,
     *     item_code: string,
     *     delivery: string
     * }  $parsedRow
     * @return array{
     *     status: OrderImportRowStatus,
     *     validation_errors: ?string,
     *     normalized_tp_1: ?string,
     *     normalized_tp_2: ?string,
     *     product_variant_id: ?int,
     *     supplier_id: ?int,
     *     market_id: ?int,
     *     quantity: ?int,
     *     unit_selling_price: ?float,
     *     courier_fee_amount: ?float
     * }
     */
    public function validateParsedRow(User $actor, array $parsedRow): array
    {
        $this->assertActorContext($actor);
        $commercialReseller = $this->resolveCommercialReseller($actor);

        return $this->validateRow($actor, $commercialReseller, $parsedRow);
    }

    private function createOrderForRow(User $actor, OrderImportRow $row)
    {
        if (
            $row->market_id === null
            || $row->supplier_id === null
            || $row->product_variant_id === null
            || $row->quantity === null
            || $row->unit_selling_price === null
        ) {
            throw ValidationException::withMessages([
                'row' => ['Import row '.$row->row_number.' is missing resolved order data.'],
            ]);
        }

        try {
            return $this->manualOrderService->create($actor, [
                'market_id' => (int) $row->market_id,
                'supplier_id' => (int) $row->supplier_id,
                'customer_name' => (string) $row->raw_name,
                'primary_phone' => (string) $row->raw_tp_1,
                'secondary_phone' => $row->raw_tp_2,
                'address_line1' => (string) $row->raw_address,
                'full_address_text' => (string) $row->raw_address,
                'city_name' => null,
                'district_name' => null,
                'items' => [[
                    'product_variant_id' => (int) $row->product_variant_id,
                    'quantity' => (int) $row->quantity,
                    'selected_selling_price' => (float) $row->unit_selling_price,
                ]],
                'discount_amount' => 0,
                'courier_id' => null,
                'courier_fee_amount' => (float) ($row->courier_fee_amount ?? 0),
                'duplicate_warning_overridden' => true,
                'after_hours_warning_shown' => true,
                'intent' => ResellerManualOrderService::INTENT_CONFIRM,
                'assignment_target' => ResellerManualOrderService::ASSIGNMENT_UNASSIGNED,
            ]);
        } catch (DuplicateOrderWarningException $e) {
            // Should not occur when override is true; surface as a clear failure.
            throw ValidationException::withMessages([
                'row' => ['Potential duplicate order blocked import row '.$row->row_number.'.'],
            ]);
        } catch (ConflictingCustomerIdentityException $e) {
            throw ValidationException::withMessages([
                'row' => [
                    'Primary and secondary phones belong to different customers on row '
                    .$row->row_number.'.',
                ],
            ]);
        }
    }

    /**
     * @param  array{
     *     row_number: int,
     *     name: string,
     *     address: string,
     *     tp_1: string,
     *     tp_2: string,
     *     price: string,
     *     qty: string,
     *     item_code: string,
     *     delivery: string
     * }  $parsedRow
     * @return array{
     *     status: OrderImportRowStatus,
     *     validation_errors: ?string,
     *     normalized_tp_1: ?string,
     *     normalized_tp_2: ?string,
     *     product_variant_id: ?int,
     *     supplier_id: ?int,
     *     market_id: ?int,
     *     quantity: ?int,
     *     unit_selling_price: ?float,
     *     courier_fee_amount: ?float
     * }
     */
    private function validateRow(User $actor, User $commercialReseller, array $parsedRow): array
    {
        $errors = [];
        $normalizedTp1 = null;
        $normalizedTp2 = null;
        $variantId = null;
        $supplierId = null;
        $marketId = null;
        $quantity = null;
        $unitSellingPrice = null;
        $courierFee = null;
        $country = null;

        $name = trim($parsedRow['name']);
        $address = trim($parsedRow['address']);
        $tp1 = trim($parsedRow['tp_1']);
        $tp2 = trim($parsedRow['tp_2']);
        $priceRaw = trim($parsedRow['price']);
        $qtyRaw = trim($parsedRow['qty']);
        $itemCode = trim($parsedRow['item_code']);
        $deliveryRaw = trim($parsedRow['delivery']);

        if ($name === '') {
            $errors[] = 'Customer name is required.';
        }

        if ($address === '') {
            $errors[] = 'Address is required.';
        }

        if ($tp1 === '') {
            $errors[] = 'Primary phone (tp_1) is required.';
        }

        $priceNormalized = $this->normalizeMoneyString($priceRaw);
        if ($priceRaw === '') {
            $errors[] = 'Price is required.';
        } elseif ($priceNormalized === null || $priceNormalized < 0) {
            $errors[] = 'Price must be a valid non-negative amount.';
        }

        if ($qtyRaw === '') {
            $errors[] = 'Quantity is required.';
        } elseif (! $this->isPositiveInteger($qtyRaw)) {
            $errors[] = 'Quantity must be a positive integer.';
        } else {
            $quantity = (int) $qtyRaw;
        }

        if ($itemCode === '') {
            $errors[] = 'Item code (product variant ID) is required.';
        } elseif (! $this->isPositiveInteger($itemCode)) {
            $errors[] = 'Item code must be a valid product variant ID.';
        } else {
            $variantId = (int) $itemCode;
        }

        $deliveryNormalized = $this->normalizeMoneyString($deliveryRaw);
        if ($deliveryRaw === '') {
            $courierFee = 0.0;
        } elseif ($deliveryNormalized === null || $deliveryNormalized < 0) {
            $errors[] = 'Delivery (courier fee) must be a valid non-negative amount.';
        } else {
            $courierFee = $deliveryNormalized;
        }

        $variant = null;
        if ($variantId !== null) {
            $variant = ProductVariant::query()
                ->with(['product.market.country'])
                ->find($variantId);

            if ($variant === null || $variant->product === null) {
                $errors[] = 'Product variant does not exist.';
                $variant = null;
            } elseif (! (bool) $variant->is_active) {
                $errors[] = 'Product variant is inactive.';
                $variant = null;
            } elseif ($variant->product->status !== ProductStatus::ACTIVE) {
                $errors[] = 'Product is not active.';
                $variant = null;
            } elseif (! (bool) $variant->product->system_visible) {
                $errors[] = 'Product is not available for ordering.';
                $variant = null;
            } else {
                $marketId = (int) $variant->product->market_id;
                $supplierId = (int) $variant->product->supplier_id;
                $market = $variant->product->market;
                $country = $market?->country;

                if ($market === null || $country === null) {
                    $errors[] = 'Product market could not be resolved.';
                } elseif (! $this->marketAccessService->hasMarketAccess($actor->company, $marketId)) {
                    $errors[] = 'Product variant market is not accessible to this reseller.';
                } elseif (! $this->supplierAssignmentService->isSupplierAssigned(
                    (int) $commercialReseller->id,
                    $supplierId
                )) {
                    $errors[] = 'Product variant supplier is not assigned to this reseller.';
                }
            }
        }

        if ($country === null && $errors === []) {
            // Fallback country for phone checks when variant failed earlier.
            $country = Country::query()->where('iso_code', 'LK')->first();
        }

        if ($tp1 !== '' && $country !== null) {
            try {
                $normalizedTp1 = $this->phoneNormalizer->normalize($tp1, $country);
                if (! $this->isLikelyValidPhone($normalizedTp1, $country)) {
                    $errors[] = 'Primary phone is invalid.';
                    $normalizedTp1 = null;
                }
            } catch (InvalidArgumentException) {
                $errors[] = 'Primary phone could not be normalized.';
            }
        }

        if ($tp2 !== '' && $country !== null) {
            try {
                $normalizedTp2 = $this->phoneNormalizer->normalize($tp2, $country);
                if (! $this->isLikelyValidPhone($normalizedTp2, $country)) {
                    $errors[] = 'Secondary phone is invalid.';
                    $normalizedTp2 = null;
                }
            } catch (InvalidArgumentException) {
                $errors[] = 'Secondary phone could not be normalized.';
            }
        }

        $banReasons = [];
        if ($normalizedTp1 !== null && $country !== null) {
            $primaryBan = $this->customerBanService->findBanByRawPhone($tp1, (int) $country->id);
            if ($primaryBan['is_banned']) {
                $banReasons[] = 'Primary phone is banned';
            }
        }

        if ($normalizedTp2 !== null && $country !== null) {
            $secondaryBan = $this->customerBanService->findBanByRawPhone($tp2, (int) $country->id);
            if ($secondaryBan['is_banned']) {
                $banReasons[] = 'Secondary phone is banned';
            }
        }

        if ($banReasons !== []) {
            $reasonParts = $banReasons;
            if ($errors !== []) {
                $reasonParts = array_merge($reasonParts, $errors);
            }

            return [
                'status' => OrderImportRowStatus::BANNED,
                'validation_errors' => implode('; ', $reasonParts),
                'normalized_tp_1' => $normalizedTp1,
                'normalized_tp_2' => $normalizedTp2,
                'product_variant_id' => $variant?->id,
                'supplier_id' => $supplierId,
                'market_id' => $marketId,
                'quantity' => $quantity,
                'unit_selling_price' => null,
                'courier_fee_amount' => $courierFee,
            ];
        }

        if ($tp1 !== '' && $tp2 !== '' && $country !== null && $normalizedTp1 !== null && $normalizedTp2 !== null) {
            try {
                $this->assertNoPhoneConflict($tp1, $tp2, (int) $country->id);
            } catch (ConflictingCustomerIdentityException) {
                $errors[] = 'Primary and secondary phones belong to different customers.';
            }
        }

        if ($variant !== null && $priceNormalized !== null && $priceNormalized >= 0) {
            $importedPrice = $priceNormalized;
            $priceLocked = (bool) $variant->product->price_locked;

            if ($priceLocked) {
                $expected = round((float) $variant->selling_price, 2);
                if (abs($importedPrice - $expected) > 0.001) {
                    $errors[] = 'Locked product price must be '.$expected.'.';
                } else {
                    $unitSellingPrice = $expected;
                }
            } else {
                try {
                    $unitSellingPrice = ResellerProductPricing::customerUnitPrice(
                        $variant,
                        false,
                        $importedPrice,
                    );
                } catch (InvalidArgumentException $e) {
                    $errors[] = match ($e->getMessage()) {
                        ResellerProductPricing::ERROR_SELECTED_PRICE_REQUIRED =>
                            'A selling price is required for unlocked products.',
                        ResellerProductPricing::ERROR_SUGGESTED_RANGE_UNAVAILABLE =>
                            'This product variant has no valid selling price range.',
                        ResellerProductPricing::ERROR_SELECTED_PRICE_OUT_OF_RANGE =>
                            'Imported price must be between the suggested minimum and maximum.',
                        default => 'Imported price is invalid.',
                    };
                }
            }
        }

        if ($errors !== []) {
            return [
                'status' => OrderImportRowStatus::INVALID,
                'validation_errors' => implode(' ', $errors),
                'normalized_tp_1' => $normalizedTp1,
                'normalized_tp_2' => $normalizedTp2,
                'product_variant_id' => $variant?->id,
                'supplier_id' => $supplierId,
                'market_id' => $marketId,
                'quantity' => $quantity,
                'unit_selling_price' => $unitSellingPrice,
                'courier_fee_amount' => $courierFee,
            ];
        }

        return [
            'status' => OrderImportRowStatus::VALID,
            'validation_errors' => null,
            'normalized_tp_1' => $normalizedTp1,
            'normalized_tp_2' => $normalizedTp2,
            'product_variant_id' => (int) $variant->id,
            'supplier_id' => $supplierId,
            'market_id' => $marketId,
            'quantity' => $quantity,
            'unit_selling_price' => $unitSellingPrice,
            'courier_fee_amount' => $courierFee,
        ];
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

    private function normalizeMoneyString(string $value): ?float
    {
        $value = trim(str_replace(',', '', $value));
        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function isPositiveInteger(string $value): bool
    {
        return preg_match('/^[1-9]\d*$/', trim($value)) === 1;
    }

    private function isLikelyValidPhone(string $normalized, Country $country): bool
    {
        $digits = preg_replace('/\D+/', '', $normalized) ?? '';
        if ($digits === '') {
            return false;
        }

        // Reuse registration-rule normalization acceptance: Sri Lanka local numbers are 10 digits.
        if (strtoupper((string) $country->iso_code) === 'LK') {
            return strlen($digits) === 10 && str_starts_with($digits, '0');
        }

        return strlen($digits) >= 8;
    }

    private function storeUpload(UploadedFile $file, int $companyId): string
    {
        $directory = 'order-imports/'.$companyId.'/'.now()->format('Y/m');
        $filename = (string) Str::uuid().'.'.strtolower((string) $file->getClientOriginalExtension());

        return Storage::disk('local')->putFileAs($directory, $file, $filename);
    }

    private function assertActorContext(User $actor): void
    {
        if ($actor->company_id === null) {
            throw ValidationException::withMessages([
                'reseller_company_id' => ['Authenticated user has no company context.'],
            ]);
        }

        if ($this->ccaEligibilityService->isEligible($actor, (int) $actor->company_id)) {
            throw ValidationException::withMessages([
                'order' => ['Call center agents cannot import orders. Use Create Order instead.'],
            ]);
        }
    }

    private function assertBatchOwnership(User $actor, OrderImportBatch $batch): void
    {
        if ((int) $batch->reseller_company_id !== (int) $actor->company_id) {
            throw ValidationException::withMessages([
                'batch' => ['Import batch not found for this company.'],
            ]);
        }
    }

    private function resolveCommercialReseller(User $actor): User
    {
        $actor->loadMissing('company.owner');

        if ($this->ccaEligibilityService->isEligible($actor, (int) $actor->company_id)) {
            $owner = $actor->company?->owner;
            if ($owner === null) {
                throw ValidationException::withMessages([
                    'reseller_id' => ['Unable to resolve the reseller owner for this company.'],
                ]);
            }

            return $owner;
        }

        return $actor;
    }

    private function formatImportItemName(OrderImportRow $row): ?string
    {
        $productName = trim((string) ($row->variant?->product?->name ?? ''));
        $variantName = trim((string) ($row->variant?->name ?? ''));

        if ($productName !== '' && $variantName !== '') {
            return $productName.' / '.$variantName;
        }

        if ($productName !== '') {
            return $productName;
        }

        if ($variantName !== '') {
            return $variantName;
        }

        $itemCode = trim((string) ($row->raw_item_code ?? ''));

        return $itemCode !== '' ? $itemCode : null;
    }

    /**
     * @return array{batch: array<string, mixed>, rows: list<array<string, mixed>>, summary: array<string, int>}
     */
    private function serializeBatch(OrderImportBatch $batch): array
    {
        $batch->loadMissing([
            'rows.createdOrder',
            'rows.variant.product',
            'rows.supplier.company',
        ]);

        $variantIds = $batch->rows
            ->pluck('product_variant_id')
            ->filter(static fn ($id) => $id !== null)
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $stockByVariant = $this->stockService->remainingStockForVariants($variantIds);

        $rows = $batch->rows
            ->sortBy('row_number')
            ->values()
            ->map(function (OrderImportRow $row) use ($stockByVariant) {
                $status = $row->status instanceof OrderImportRowStatus
                    ? $row->status
                    : OrderImportRowStatus::from((string) $row->status);

                $variantId = $row->product_variant_id !== null ? (int) $row->product_variant_id : null;
                $qty = $row->quantity !== null
                    ? (int) $row->quantity
                    : (is_numeric((string) $row->raw_qty) ? (int) $row->raw_qty : null);
                $unitAmount = $row->unit_selling_price !== null
                    ? round((float) $row->unit_selling_price, 2)
                    : $this->normalizeMoneyString((string) ($row->raw_price ?? ''));
                $lineAmount = ($unitAmount !== null && $qty !== null)
                    ? round($unitAmount * $qty, 2)
                    : null;

                return [
                    'row' => (int) $row->row_number,
                    'name' => (string) ($row->raw_name ?? ''),
                    'tp_1' => (string) ($row->raw_tp_1 ?? ''),
                    'tp_2' => (string) ($row->raw_tp_2 ?? ''),
                    'address' => (string) ($row->raw_address ?? ''),
                    'price' => (string) ($row->raw_price ?? ''),
                    'qty' => (string) ($row->raw_qty ?? ''),
                    'item_code' => (string) ($row->raw_item_code ?? ''),
                    'delivery' => (string) ($row->raw_delivery ?? ''),
                    'state' => $status->cssClass(),
                    'stateLabel' => $status->label(),
                    'reason' => (string) ($row->validation_errors ?? ''),
                    'created_order_id' => $row->created_order_id !== null ? (int) $row->created_order_id : null,
                    'order_number' => $row->createdOrder?->order_number !== null
                        ? (string) $row->createdOrder->order_number
                        : null,
                    'item_name' => $this->formatImportItemName($row),
                    'item_qty' => $qty,
                    'item_amount' => $lineAmount !== null
                        ? number_format($lineAmount, 2, '.', '')
                        : null,
                    'supplier_name' => $row->supplier !== null
                        ? (string) ($row->supplier->company?->name ?? ('Supplier #'.$row->supplier_id))
                        : null,
                    'existing_stock' => $variantId !== null
                        ? (int) ($stockByVariant[$variantId] ?? 0)
                        : null,
                ];
            })
            ->all();

        $statusCounts = [
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'banned_rows' => 0,
            'created_rows' => 0,
        ];

        foreach ($batch->rows as $row) {
            $status = $row->status instanceof OrderImportRowStatus
                ? $row->status
                : OrderImportRowStatus::from((string) $row->status);

            match ($status) {
                OrderImportRowStatus::VALID => $statusCounts['valid_rows']++,
                OrderImportRowStatus::INVALID => $statusCounts['invalid_rows']++,
                OrderImportRowStatus::BANNED => $statusCounts['banned_rows']++,
                OrderImportRowStatus::CREATED => $statusCounts['created_rows']++,
            };
        }

        $summary = [
            'total_rows' => (int) $batch->total_rows,
            ...$statusCounts,
        ];

        return [
            'batch' => [
                'uuid' => (string) $batch->uuid,
                'status' => $batch->status instanceof OrderImportBatchStatus
                    ? $batch->status->value
                    : (string) $batch->status,
                'original_filename' => (string) $batch->original_filename,
                'file_type' => (string) $batch->file_type,
                ...$summary,
            ],
            'rows' => $rows,
            'summary' => $summary,
        ];
    }
}
