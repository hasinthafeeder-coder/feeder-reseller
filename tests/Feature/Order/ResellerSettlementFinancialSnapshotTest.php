<?php

namespace Tests\Feature\Order;

use Feeder\Core\Contracts\Courier\CourierBookingAdapter;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\OrderSource;
use Feeder\Core\Enums\OrderType;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Enums\ShipmentStatus;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Courier;
use Feeder\Core\Models\CourierCity;
use Feeder\Core\Models\CourierMarketPricing;
use Feeder\Core\Models\CourierService;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\Shipment;
use Feeder\Core\Models\SupplierCourierAccount;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\ProductCategory;
use Feeder\Core\Models\ProductVariant;
use Feeder\Core\Models\ResellerSupplierAssignment;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Courier\CourierServiceFixedChargeService;
use Feeder\Core\Services\Order\OrderNumberGenerator;
use Feeder\Core\Services\Order\OrderService;
use Feeder\Core\Services\Order\ShipmentBookingService;
use Feeder\Core\Services\ResellerServiceChargeService;
use Feeder\Core\Services\Settlement\ResellerSettlementFinancialCalculator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

/**
 * Order-time financial snapshots for reseller settlement foundations.
 *
 * Uses OrderService directly so capture logic is tested without HTTP/courier
 * draft side-effects. Order numbers are mocked to avoid sequence collisions
 * with committed rows outside the test transaction.
 */
class ResellerSettlementFinancialSnapshotTest extends TestCase
{
    use SetsUpMarketData;
    use UsesMysqlTestDatabase;

    private int $orderNumberSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedMarketLookups();
        $this->mockOrderNumberGenerator();
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_order_create_snapshots_service_charge_commission_and_courier_fixed_charge(): void
    {
        $reseller = $this->makeResellerUser();
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);

        $variantA = $this->makeVariant($supplier, [
            'cost' => 100.00,
            'selling_price' => 500.00,
            'company_commission' => 150.00,
        ]);
        $variantB = $this->makeVariant($supplier, [
            'cost' => 80.00,
            'selling_price' => 400.00,
            'company_commission' => 50.00,
        ], 'Second Product');

        $courierSetup = $this->makeCourierWithFixedCharge('90.00', $supplier);
        $countryId = $this->countryByIso('LK')->id;

        $order = app(OrderService::class)->create([
            'source' => OrderSource::MANUAL,
            'order_type' => OrderType::NEW,
            'market_id' => $this->marketByCode('lk')->id,
            'reseller_id' => $reseller->id,
            'reseller_company_id' => $reseller->company_id,
            'supplier_id' => $supplier->id,
            'created_by' => $reseller->id,
            'draft_courier_id' => $courierSetup['courier']->id,
            'draft_courier_service_id' => $courierSetup['service']->id,
            'customer' => [
                'display_name' => 'Snapshot Customer',
                'primary_country_id' => $countryId,
                'primary_phone' => '070'.random_int(1000000, 9999999),
                'primary_phone_country_id' => $countryId,
            ],
            'address' => [
                'recipient_name' => 'Snapshot Customer',
                'line1' => '12 Test Street',
                'city_name' => 'Colombo',
                'district_name' => 'Colombo',
                'country_id' => $countryId,
            ],
            'items' => [
                [
                    'product_variant_id' => $variantA->id,
                    'quantity' => 2,
                    'unit_selling_price' => 500.00,
                ],
                [
                    'product_variant_id' => $variantB->id,
                    'quantity' => 1,
                    'unit_selling_price' => 400.00,
                ],
            ],
            'discount_amount' => 0,
            'courier_fee_amount' => 200.00,
        ]);

        $order->load('items');

        $this->assertSame('75.00', (string) $order->reseller_service_charge_snapshot);
        $this->assertSame('90.00', (string) $order->reseller_courier_charge_snapshot);
        $this->assertSame('350.00', (string) $order->company_commission_amount);

        $itemsByVariant = $order->items->keyBy('product_variant_id');
        $this->assertSame('150.00', (string) $itemsByVariant[$variantA->id]->unit_company_commission_snapshot);
        $this->assertSame('100.00', (string) $itemsByVariant[$variantA->id]->unit_cost_snapshot);
        $this->assertSame('50.00', (string) $itemsByVariant[$variantB->id]->unit_company_commission_snapshot);

        $calculator = app(ResellerSettlementFinancialCalculator::class);
        $result = $calculator->calculateGrossResellerProfitBeforeDebt($order);

        $this->assertTrue($result['calculable']);
        $this->assertSame('1600.00', $result['cod_amount']); // 1400 items + 200 courier fee
        $this->assertSame('280.00', $result['supplier_cost']); // (100 * 2) + (80 * 1)
        $this->assertSame('75.00', $result['service_charge']);
        $this->assertSame('90.00', $result['courier_charge']);
        $this->assertSame('350.00', $result['company_commission']);
        // 1600 - 280 supplier cost - 90 courier - 75 service - 350 commission
        $this->assertSame('805.00', $result['gross_reseller_profit']);
        $this->assertNotEquals(
            (string) $order->courier_fee_amount,
            $result['courier_charge'],
            'Settlement courier charge must not use weight-based courier_fee_amount'
        );
    }

    public function test_changing_service_charge_default_does_not_rewrite_order_snapshot(): void
    {
        $order = $this->createMinimalOrder();

        $this->assertSame('75.00', (string) $order->reseller_service_charge_snapshot);

        app(ResellerServiceChargeService::class)->setDefaultCharge('lk', '200.00');

        $this->assertSame('75.00', (string) $order->fresh()->reseller_service_charge_snapshot);
        $this->assertSame('200.00', app(ResellerServiceChargeService::class)->getDefaultCharge('lk'));
    }

    public function test_changing_live_variant_cost_and_commission_does_not_rewrite_order_item_snapshots(): void
    {
        $reseller = $this->makeResellerUser();
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier, [
            'cost' => 100.00,
            'selling_price' => 500.00,
            'company_commission' => 150.00,
        ]);

        $order = $this->createMinimalOrder($reseller, $supplier, $variant);
        $item = $order->items->first();

        $variant->forceFill([
            'cost' => 999.00,
            'company_commission' => 888.00,
        ])->save();

        $item->refresh();
        $this->assertSame('100.00', (string) $item->unit_cost_snapshot);
        $this->assertSame('150.00', (string) $item->unit_company_commission_snapshot);
        $this->assertSame('300.00', (string) $order->fresh()->company_commission_amount);
    }

    public function test_changing_courier_fixed_charge_does_not_rewrite_existing_order_snapshot(): void
    {
        $reseller = $this->makeResellerUser();
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $courierSetup = $this->makeCourierWithFixedCharge('55.00', $supplier);
        $order = $this->createMinimalOrder(
            $reseller,
            $supplier,
            courierServiceId: $courierSetup['service']->id,
            courierId: $courierSetup['courier']->id,
        );

        $this->assertSame('55.00', (string) $order->reseller_courier_charge_snapshot);

        app(CourierServiceFixedChargeService::class)->setFixedCharge($courierSetup['service'], '250.00');

        $this->assertSame('55.00', (string) $order->fresh()->reseller_courier_charge_snapshot);
        $this->assertSame('250.00', (string) $courierSetup['service']->fresh()->reseller_fixed_charge);
    }

    public function test_order_edit_preserves_unit_snapshots_after_live_cost_and_commission_change(): void
    {
        $reseller = $this->makeResellerUser();
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier, [
            'cost' => 100.00,
            'selling_price' => 500.00,
            'company_commission' => 150.00,
        ]);

        $order = $this->createMinimalOrder($reseller, $supplier, $variant);
        $originalServiceCharge = (string) $order->reseller_service_charge_snapshot;

        $variant->forceFill([
            'cost' => 999.00,
            'company_commission' => 888.00,
            'selling_price' => 700.00,
        ])->save();

        $updated = app(OrderService::class)->updateDetails(
            $order,
            $this->editPayloadFromOrder($order, [
                'items' => [[
                    'product_variant_id' => $variant->id,
                    'quantity' => 2,
                    'unit_selling_price' => 500.00,
                ]],
            ]),
            (int) $reseller->id,
            (int) $reseller->company_id,
        );

        $item = $updated->items->first();
        $this->assertSame('100.00', (string) $item->unit_cost_snapshot);
        $this->assertSame('150.00', (string) $item->unit_company_commission_snapshot);
        $this->assertSame('300.00', (string) $updated->company_commission_amount);
        $this->assertSame($originalServiceCharge, (string) $updated->reseller_service_charge_snapshot);
    }

    public function test_order_edit_quantity_change_preserves_unit_snapshots_and_recalculates_totals(): void
    {
        $reseller = $this->makeResellerUser();
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier, [
            'cost' => 100.00,
            'selling_price' => 500.00,
            'company_commission' => 150.00,
        ]);

        $order = $this->createMinimalOrder($reseller, $supplier, $variant);

        $variant->forceFill([
            'cost' => 40.00,
            'company_commission' => 10.00,
        ])->save();

        $updated = app(OrderService::class)->updateDetails(
            $order,
            $this->editPayloadFromOrder($order, [
                'items' => [[
                    'product_variant_id' => $variant->id,
                    'quantity' => 3,
                    'unit_selling_price' => 500.00,
                ]],
            ]),
            (int) $reseller->id,
            (int) $reseller->company_id,
        );

        $item = $updated->items->first();
        $this->assertSame(3, (int) $item->quantity);
        $this->assertSame('100.00', (string) $item->unit_cost_snapshot);
        $this->assertSame('150.00', (string) $item->unit_company_commission_snapshot);
        $this->assertSame('1500.00', (string) $updated->items_subtotal);
        $this->assertSame('450.00', (string) $updated->company_commission_amount);
        $this->assertSame('1500.00', (string) $updated->customer_payable_amount);
    }

    public function test_order_edit_adding_new_variant_captures_live_financial_snapshots(): void
    {
        $reseller = $this->makeResellerUser();
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variantA = $this->makeVariant($supplier, [
            'cost' => 100.00,
            'selling_price' => 500.00,
            'company_commission' => 150.00,
        ]);
        $variantB = $this->makeVariant($supplier, [
            'cost' => 80.00,
            'selling_price' => 400.00,
            'company_commission' => 50.00,
        ], 'Added Product');

        $order = $this->createMinimalOrder($reseller, $supplier, $variantA);

        $variantA->forceFill([
            'cost' => 999.00,
            'company_commission' => 888.00,
        ])->save();

        $updated = app(OrderService::class)->updateDetails(
            $order,
            $this->editPayloadFromOrder($order, [
                'items' => [
                    [
                        'product_variant_id' => $variantA->id,
                        'quantity' => 2,
                        'unit_selling_price' => 500.00,
                    ],
                    [
                        'product_variant_id' => $variantB->id,
                        'quantity' => 1,
                        'unit_selling_price' => 400.00,
                    ],
                ],
            ]),
            (int) $reseller->id,
            (int) $reseller->company_id,
        );

        $itemsByVariant = $updated->items->keyBy('product_variant_id');
        $this->assertSame('100.00', (string) $itemsByVariant[$variantA->id]->unit_cost_snapshot);
        $this->assertSame('150.00', (string) $itemsByVariant[$variantA->id]->unit_company_commission_snapshot);
        $this->assertSame('80.00', (string) $itemsByVariant[$variantB->id]->unit_cost_snapshot);
        $this->assertSame('50.00', (string) $itemsByVariant[$variantB->id]->unit_company_commission_snapshot);
        $this->assertSame('350.00', (string) $updated->company_commission_amount);
    }

    public function test_order_edit_replacing_variant_captures_replacement_snapshots_without_transfer(): void
    {
        $reseller = $this->makeResellerUser();
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variantA = $this->makeVariant($supplier, [
            'cost' => 100.00,
            'selling_price' => 500.00,
            'company_commission' => 150.00,
        ]);
        $variantB = $this->makeVariant($supplier, [
            'cost' => 60.00,
            'selling_price' => 300.00,
            'company_commission' => 40.00,
        ], 'Replacement Product');

        $order = $this->createMinimalOrder($reseller, $supplier, $variantA);

        $variantA->forceFill([
            'cost' => 1.00,
            'company_commission' => 2.00,
        ])->save();
        $variantB->forceFill([
            'cost' => 60.00,
            'company_commission' => 40.00,
        ])->save();

        $updated = app(OrderService::class)->updateDetails(
            $order,
            $this->editPayloadFromOrder($order, [
                'items' => [[
                    'product_variant_id' => $variantB->id,
                    'quantity' => 2,
                    'unit_selling_price' => 300.00,
                ]],
            ]),
            (int) $reseller->id,
            (int) $reseller->company_id,
        );

        $item = $updated->items->first();
        $this->assertSame((int) $variantB->id, (int) $item->product_variant_id);
        $this->assertSame('60.00', (string) $item->unit_cost_snapshot);
        $this->assertSame('40.00', (string) $item->unit_company_commission_snapshot);
        $this->assertNotSame('100.00', (string) $item->unit_cost_snapshot);
        $this->assertNotSame('150.00', (string) $item->unit_company_commission_snapshot);
        $this->assertSame('80.00', (string) $updated->company_commission_amount);
    }

    public function test_order_edit_preserves_reseller_service_charge_snapshot_when_default_changes(): void
    {
        $order = $this->createMinimalOrder();
        $this->assertSame('75.00', (string) $order->reseller_service_charge_snapshot);

        app(ResellerServiceChargeService::class)->setDefaultCharge('lk', '200.00');

        $updated = app(OrderService::class)->updateDetails(
            $order,
            $this->editPayloadFromOrder($order),
            (int) $order->reseller_id,
            (int) $order->reseller_company_id,
        );

        $this->assertSame('75.00', (string) $updated->reseller_service_charge_snapshot);
        $this->assertSame('200.00', app(ResellerServiceChargeService::class)->getDefaultCharge('lk'));
    }

    public function test_order_edit_remains_blocked_after_shipment_booking(): void
    {
        $reseller = $this->makeResellerUser();
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);
        $order = $this->createMinimalOrder($reseller, $supplier, $variant);
        $courierSetup = $this->makeCourierWithFixedCharge('10.00', $supplier, withCity: true);

        Shipment::query()->create([
            'order_id' => $order->id,
            'supplier_id' => $order->supplier_id,
            'courier_id' => $courierSetup['courier']->id,
            'courier_service_id' => $courierSetup['service']->id,
            'courier_city_id' => $courierSetup['city']->id,
            'tracking_number' => 'TRK-LOCK-1',
            'weight_snapshot' => 0.5,
            'courier_fee_snapshot' => 200.00,
            'currency_id' => $order->currency_id,
            'status' => ShipmentStatus::BOOKED,
            'booked_at' => now(),
            'booked_by' => $reseller->id,
        ]);

        try {
            app(OrderService::class)->updateDetails(
                $order->fresh(['items', 'shipment', 'address']),
                $this->editPayloadFromOrder($order->fresh(['items', 'address'])),
                (int) $reseller->id,
                (int) $reseller->company_id,
            );
            $this->fail('Expected shipment lock rejection on order edit.');
        } catch (ValidationException $e) {
            $messages = $e->errors();
            $this->assertTrue(
                isset($messages['order']) || isset($messages['shipment']),
                'Expected order or shipment lock validation error.'
            );
        }
    }

    public function test_booking_with_configured_fixed_charge_captures_snapshot(): void
    {
        $reseller = $this->makeResellerUser();
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $order = $this->createMinimalOrder($reseller, $supplier);
        $courierSetup = $this->makeCourierWithFixedCharge('125.50', $supplier, withCity: true);

        $shipment = $this->bookOrderShipment($order, $courierSetup, 'TRK-CFG-1');

        $this->assertNotNull($shipment->id);
        $this->assertSame('125.50', (string) $order->fresh()->reseller_courier_charge_snapshot);
    }

    public function test_booking_with_unconfigured_fixed_charge_succeeds_and_keeps_null_snapshot(): void
    {
        $reseller = $this->makeResellerUser();
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $order = $this->createMinimalOrder($reseller, $supplier);
        $courierSetup = $this->makeCourierWithFixedCharge(null, $supplier, withCity: true);

        $this->assertNull($courierSetup['service']->reseller_fixed_charge);

        $shipment = $this->bookOrderShipment($order, $courierSetup, 'TRK-NULL-1');

        $this->assertNotNull($shipment->id);
        $this->assertNull($order->fresh()->reseller_courier_charge_snapshot);

        $result = app(ResellerSettlementFinancialCalculator::class)
            ->calculateGrossResellerProfitBeforeDebt($order->fresh('items'));

        $this->assertFalse($result['calculable']);
        $this->assertContains('reseller_courier_charge_snapshot', $result['missing']);
    }

    public function test_changing_fixed_charge_after_booking_does_not_rewrite_booked_order_snapshot(): void
    {
        $reseller = $this->makeResellerUser();
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $order = $this->createMinimalOrder($reseller, $supplier);
        $courierSetup = $this->makeCourierWithFixedCharge('90.00', $supplier, withCity: true);

        $this->bookOrderShipment($order, $courierSetup, 'TRK-IMMUT-1');
        $this->assertSame('90.00', (string) $order->fresh()->reseller_courier_charge_snapshot);

        app(CourierServiceFixedChargeService::class)->setFixedCharge($courierSetup['service'], '300.00');

        $this->assertSame('90.00', (string) $order->fresh()->reseller_courier_charge_snapshot);
        $this->assertSame('300.00', (string) $courierSetup['service']->fresh()->reseller_fixed_charge);
    }

    private function mockOrderNumberGenerator(): void
    {
        $generator = Mockery::mock(OrderNumberGenerator::class);
        $generator->shouldReceive('allocateNext')
            ->andReturnUsing(function (): string {
                $this->orderNumberSeq++;

                return '9'.str_pad((string) (870000000 + $this->orderNumberSeq), 11, '0', STR_PAD_LEFT);
            });

        $this->app->instance(OrderNumberGenerator::class, $generator);
    }

    private function createMinimalOrder(
        ?User $reseller = null,
        ?User $supplier = null,
        ?ProductVariant $variant = null,
        ?int $courierServiceId = null,
        ?int $courierId = null,
    ): Order {
        $reseller ??= $this->makeResellerUser();
        $supplier ??= $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant ??= $this->makeVariant($supplier, [
            'cost' => 100.00,
            'selling_price' => 500.00,
            'company_commission' => 150.00,
        ]);

        $countryId = $this->countryByIso('LK')->id;

        return app(OrderService::class)->create([
            'source' => OrderSource::MANUAL,
            'order_type' => OrderType::NEW,
            'market_id' => $this->marketByCode('lk')->id,
            'reseller_id' => $reseller->id,
            'reseller_company_id' => $reseller->company_id,
            'supplier_id' => $supplier->id,
            'created_by' => $reseller->id,
            'draft_courier_id' => $courierId,
            'draft_courier_service_id' => $courierServiceId,
            'customer' => [
                'display_name' => 'Minimal Customer',
                'primary_country_id' => $countryId,
                'primary_phone' => '070'.random_int(1000000, 9999999),
                'primary_phone_country_id' => $countryId,
            ],
            'address' => [
                'recipient_name' => 'Minimal Customer',
                'line1' => '12 Test Street',
                'city_name' => 'Colombo',
                'district_name' => 'Colombo',
                'country_id' => $countryId,
            ],
            'items' => [[
                'product_variant_id' => $variant->id,
                'quantity' => 2,
                'unit_selling_price' => 500.00,
            ]],
            'discount_amount' => 0,
            'courier_fee_amount' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function editPayloadFromOrder(Order $order, array $overrides = []): array
    {
        $order->loadMissing(['items', 'address']);

        $items = $overrides['items'] ?? $order->items->map(static fn ($item) => [
            'product_variant_id' => (int) $item->product_variant_id,
            'quantity' => (int) $item->quantity,
            'unit_selling_price' => (float) $item->unit_selling_price,
        ])->values()->all();

        return [
            'customer' => [
                'display_name' => (string) $order->customer_name_snapshot,
                'primary_country_id' => (int) $order->primary_phone_country_id,
                'primary_phone' => (string) $order->primary_phone_snapshot,
                'primary_phone_country_id' => (int) $order->primary_phone_country_id,
                'secondary_phone' => $order->secondary_phone_snapshot,
                'secondary_phone_country_id' => $order->secondary_phone_country_id,
            ],
            'address' => [
                'recipient_name' => (string) ($order->address?->recipient_name ?? $order->customer_name_snapshot),
                'line1' => (string) ($order->address?->line1 ?? '12 Test Street'),
                'line2' => $order->address?->line2,
                'city_name' => (string) ($order->address?->city_name ?? 'Colombo'),
                'district_name' => $order->address?->district_name ?? 'Colombo',
                'postal_code' => $order->address?->postal_code,
                'country_id' => (int) ($order->address?->country_id ?? $order->primary_phone_country_id),
                'full_address_text' => $order->address?->full_address_text,
            ],
            'items' => $items,
            'discount_amount' => (float) $order->discount_amount,
            'courier_fee_amount' => (float) $order->courier_fee_amount,
            'draft_courier_id' => $order->draft_courier_id,
            'draft_courier_service_id' => $order->draft_courier_service_id,
            'draft_courier_city_id' => $order->draft_courier_city_id,
            'duplicate_warning_overridden' => true,
            ...$overrides,
        ];
    }

    /**
     * @param  array{courier: Courier, service: CourierService, city: CourierCity}  $courierSetup
     */
    private function bookOrderShipment(Order $order, array $courierSetup, string $trackingNumber): Shipment
    {
        $adapter = new class($trackingNumber) implements CourierBookingAdapter
        {
            public function __construct(private readonly string $trackingNumber)
            {
            }

            public function book(
                Order $order,
                Courier $courier,
                CourierService $service,
                CourierCity $city,
                ?SupplierCourierAccount $account,
                float $weightKg,
                float $courierFee,
            ): array {
                return [
                    'tracking_number' => $this->trackingNumber,
                    'external_booking_ref' => 'EXT-'.$this->trackingNumber,
                ];
            }
        };

        return app(ShipmentBookingService::class)->book(
            $order,
            (int) $courierSetup['courier']->id,
            (int) $courierSetup['service']->id,
            (int) $courierSetup['city']->id,
            $adapter,
            (int) $order->reseller_id,
            (int) $order->reseller_company_id,
        );
    }

    /**
     * @return array{courier: Courier, service: CourierService, city?: CourierCity}
     */
    private function makeCourierWithFixedCharge(?string $charge, User $supplier, bool $withCity = false): array
    {
        $market = $this->marketByCode('lk');

        $courier = Courier::query()->create([
            'code' => 'FX'.strtoupper(substr(uniqid(), -4)),
            'name' => 'Fixed Charge Courier',
            'is_active' => true,
        ]);

        $serviceAttributes = [
            'courier_id' => $courier->id,
            'code' => 'STD',
            'name' => 'Standard',
            'external_service_id' => 'ext-std',
            'is_active' => true,
        ];

        if ($charge !== null) {
            $serviceAttributes['reseller_fixed_charge'] = $charge;
        }

        $service = CourierService::query()->create($serviceAttributes);

        CourierMarketPricing::query()->create([
            'courier_id' => $courier->id,
            'market_id' => $market->id,
            'currency_id' => $market->currency_id,
            'first_kg_fee' => 200.00,
            'additional_kg_fee' => 50.00,
            'is_active' => true,
        ]);

        SupplierCourierAccount::query()->create([
            'supplier_id' => $supplier->id,
            'courier_id' => $courier->id,
            'account_label' => 'Primary',
            'credentials_encrypted' => Crypt::encryptString(json_encode([
                'api_key' => 'secret-api-key',
            ], JSON_THROW_ON_ERROR)),
            'is_active' => true,
        ]);

        $result = compact('courier', 'service');

        if ($withCity) {
            $result['city'] = CourierCity::query()->create([
                'courier_id' => $courier->id,
                'district_name' => 'Colombo',
                'city_name' => 'Colombo 03',
                'external_city_code' => 'CMB03-'.Str::lower(Str::random(4)),
                'external_district_code' => 'COL',
                'is_active' => true,
            ]);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $variantOverrides
     */
    private function makeVariant(
        User $supplier,
        array $variantOverrides = [],
        string $productName = 'Snapshot Product',
    ): ProductVariant {
        $category = ProductCategory::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Snapshot Cat',
            'slug' => 'snap-cat-'.Str::lower(Str::random(6)),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'market_id' => $this->marketByCode('lk')->id,
            'name' => $productName.' '.Str::lower(Str::random(4)),
            'slug' => 'snap-'.Str::lower(Str::random(8)),
            'status' => ProductStatus::ACTIVE,
            'system_visible' => true,
            'web_visible' => true,
            'price_locked' => true,
            'published_at' => now(),
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ]);

        return ProductVariant::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'name' => 'Default',
            'barcode' => '1'.str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT),
            'cost' => 100.00,
            'selling_price' => 500.00,
            'weight' => 0.5,
            'company_commission' => 150.00,
            'sort_order' => 0,
            'is_active' => true,
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ], $variantOverrides));
    }

    private function assignSupplier(User $reseller, User $supplier): void
    {
        ResellerSupplierAssignment::query()->firstOrCreate([
            'reseller_id' => $reseller->id,
            'supplier_id' => $supplier->id,
        ]);
    }

    private function makeResellerUser(): User
    {
        $portal = Portal::query()->firstOrCreate(
            ['code' => PortalCode::RESELLER->value],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Reseller Portal',
                'subdomain' => 'reseller-'.Str::lower(Str::random(4)),
                'description' => 'Reseller Portal',
                'is_active' => true,
            ]
        );

        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'portal_id' => $portal->id,
            'name' => 'Reseller Co '.Str::lower(Str::random(4)),
            'email' => 'reseller-'.Str::uuid().'@feeder.local',
            'phone' => '077'.random_int(100000, 999999),
            'registration_number' => 'REG-'.Str::random(6),
            'status' => CompanyStatus::ACTIVE->value,
        ]);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'email' => $company->email,
            'phone' => $company->phone,
            'password' => Hash::make('password'),
            'user_type' => UserType::OWNER->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
        ]);

        $company->forceFill(['owner_user_id' => $user->id])->save();
        $this->configureResellerCompany($company, ['lk']);

        return $user->fresh('company');
    }

    private function makeSupplierUser(): User
    {
        $portal = Portal::query()->firstOrCreate(
            ['code' => PortalCode::SUPPLIER->value],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Supplier Portal',
                'subdomain' => 'supplier-'.Str::lower(Str::random(4)),
                'description' => 'Supplier Portal',
                'is_active' => true,
            ]
        );

        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'portal_id' => $portal->id,
            'name' => 'Supplier '.Str::upper(Str::random(4)),
            'email' => 'supplier-'.Str::uuid().'@feeder.local',
            'phone' => '077'.random_int(100000, 999999),
            'registration_number' => 'REG-'.Str::random(6),
            'status' => CompanyStatus::ACTIVE->value,
            'operation_market_id' => $this->marketByCode('lk')->id,
        ]);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'email' => $company->email,
            'phone' => $company->phone,
            'password' => Hash::make('password'),
            'user_type' => UserType::OWNER->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
        ]);

        $company->forceFill(['owner_user_id' => $user->id])->save();

        return $user->fresh('company');
    }
}
