<?php

namespace Tests\Feature\Order;

use Carbon\CarbonImmutable;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\OrderSource;
use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Enums\OrderType;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Courier;
use Feeder\Core\Models\CourierCity;
use Feeder\Core\Models\CourierMarketPricing;
use Feeder\Core\Models\CourierService;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\OrderStatusHistory;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\ProductCategory;
use Feeder\Core\Models\ProductVariant;
use Feeder\Core\Models\ResellerSupplierAssignment;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\Shipment;
use Feeder\Core\Models\SupplierCourierAccount;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Order\CustomerBanService;
use Feeder\Core\Services\Order\CustomerIdentityService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class ResellerManualOrderCreateTest extends TestCase
{
    use SetsUpMarketData;
    use UsesMysqlTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedMarketLookups();
        config(['cache.default' => 'array']);
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_create_requires_orders_create_permission(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.view']);

        $this->actingAs($reseller)
            ->get(route('orders.create'))
            ->assertForbidden();

        $this->actingAs($reseller)
            ->post(route('orders.store'), $this->minimalPayload())
            ->assertForbidden();
    }

    public function test_valid_manual_order_succeeds_as_pending_manual(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier, ['selling_price' => 250.00]);

        $phone = '070'.random_int(1000000, 9999999);

        $response = $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'primary_phone' => $phone,
            'secondary_phone' => '071'.random_int(1000000, 9999999),
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 2],
            ],
            'discount_amount' => 50,
        ]));

        $order = Order::query()->where('primary_phone_snapshot', $phone)->first();
        $this->assertNotNull($order);
        $response->assertRedirect(route('orders.show', $order));

        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame(OrderSource::MANUAL, $order->source);
        $this->assertSame(OrderType::NEW, $order->order_type);
        $this->assertSame((int) $reseller->company_id, (int) $order->reseller_company_id);
        $this->assertSame((int) $reseller->id, (int) $order->reseller_id);
        $this->assertNull($order->cca_id);
        $this->assertNull($order->draft_courier_id);
        $this->assertSame('500.00', (string) $order->items_subtotal);
        $this->assertSame('50.00', (string) $order->discount_amount);
        $this->assertSame('0.00', (string) $order->courier_fee_amount);
        $this->assertSame('450.00', (string) $order->customer_payable_amount);
        $this->assertSame(1.0, (float) $order->total_weight);
        $this->assertNotNull($order->customer_id);
        $this->assertCount(1, $order->items);
        $this->assertSame('250.00', (string) $order->items->first()->unit_selling_price);
        $this->assertNull($order->shipment);

        $history = OrderStatusHistory::query()->where('order_id', $order->id)->orderBy('id')->first();
        $this->assertNotNull($history);
        $this->assertNull($history->from_status);
        $this->assertSame(OrderStatus::PENDING, $history->to_status);
    }

    public function test_browser_selling_price_is_ignored(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier, ['selling_price' => 250.00]);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                    'unit_selling_price' => 1,
                    'selected_selling_price' => 1,
                ],
            ],
        ]))->assertRedirect();

        $order = Order::query()->latest('id')->first();
        $this->assertSame('250.00', (string) $order->items_subtotal);
        $this->assertSame('250.00', (string) $order->items->first()->unit_selling_price);
    }

    public function test_locked_product_uses_selling_price_and_ignores_selected_price(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier, [
            'cost' => 1000.00,
            'selling_price' => 2500.00,
            'company_commission' => 150.00,
        ], 'lk', true);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                    'selected_selling_price' => 9999.00,
                ],
            ],
            'discount_amount' => 0,
        ]))->assertRedirect();

        $order = Order::query()->where('reseller_company_id', $reseller->company_id)->latest('id')->first();
        $item = $order->items->first();

        $this->assertSame('2500.00', (string) $item->unit_selling_price);
        $this->assertSame('2500.00', (string) $order->items_subtotal);
        $this->assertSame(
            1350.0,
            \Feeder\Core\Support\ResellerProductPricing::unitProfit($variant, 2500.00)
        );
    }

    public function test_unlocked_product_accepts_selected_price_within_range(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier, [
            'cost' => 1000.00,
            'selling_price' => 0.00,
            'suggested_price_min' => 2500.00,
            'suggested_price_max' => 3500.00,
            'company_commission' => 150.00,
        ], 'lk', false);

        foreach ([2500.00, 3000.00, 3500.00] as $selected) {
            $phone = '070'.random_int(1000000, 9999999);

            $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
                'supplier_id' => $supplier->id,
                'primary_phone' => $phone,
                'items' => [
                    [
                        'product_variant_id' => $variant->id,
                        'quantity' => 2,
                        'selected_selling_price' => $selected,
                    ],
                ],
                'discount_amount' => 100,
                'courier_id' => null,
            ]))->assertRedirect();

            $order = Order::query()->where('primary_phone_snapshot', $phone)->first();
            $this->assertNotNull($order);
            $this->assertSame(number_format($selected, 2, '.', ''), (string) $order->items->first()->unit_selling_price);
            $this->assertSame(number_format($selected * 2, 2, '.', ''), (string) $order->items_subtotal);
            $this->assertSame(number_format(($selected * 2) - 100, 2, '.', ''), (string) $order->customer_payable_amount);
            $this->assertSame(
                round($selected - 1000 - 150, 2),
                \Feeder\Core\Support\ResellerProductPricing::unitProfit($variant, $selected)
            );
        }
    }

    public function test_unlocked_product_rejects_selected_price_outside_range(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier, [
            'cost' => 1000.00,
            'selling_price' => 0.00,
            'suggested_price_min' => 2500.00,
            'suggested_price_max' => 3500.00,
            'company_commission' => 150.00,
        ], 'lk', false);

        foreach ([2499.99, 3500.01] as $selected) {
            $before = Order::query()->count();

            $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
                'supplier_id' => $supplier->id,
                'items' => [
                    [
                        'product_variant_id' => $variant->id,
                        'quantity' => 1,
                        'selected_selling_price' => $selected,
                    ],
                ],
            ]))->assertRedirect(route('orders.create'))
                ->assertSessionHasErrors(['items.0.selected_selling_price']);

            $this->assertSame($before, Order::query()->count());
        }
    }

    public function test_unlocked_product_rejects_missing_selected_price(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier, [
            'cost' => 1000.00,
            'selling_price' => 0.00,
            'suggested_price_min' => 2500.00,
            'suggested_price_max' => 3500.00,
            'company_commission' => 150.00,
        ], 'lk', false);

        $before = Order::query()->count();

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'items' => [
                [
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                ],
            ],
        ]))->assertRedirect(route('orders.create'))
            ->assertSessionHasErrors(['items.0.selected_selling_price']);

        $this->assertSame($before, Order::query()->count());
    }

    public function test_catalog_variants_expose_authoritative_pricing_fields(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $locked = $this->makeVariant($supplier, [
            'cost' => 1000.00,
            'selling_price' => 2500.00,
            'company_commission' => 150.00,
        ], 'lk', true);
        $unlocked = $this->makeVariant($supplier, [
            'cost' => 1000.00,
            'selling_price' => 0.00,
            'suggested_price_min' => 2500.00,
            'suggested_price_max' => 3500.00,
            'company_commission' => 150.00,
        ], 'lk', false);

        $this->actingAs($reseller)
            ->getJson(route('orders.catalog.variants', [
                'market_id' => $this->marketByCode('lk')->id,
                'supplier_id' => $supplier->id,
                'product_id' => $locked->product_id,
            ]))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $locked->id,
                'price_locked' => true,
                'cost' => '1000.00',
                'company_commission' => '150.00',
                'selling_price' => '2500.00',
                'customer_unit_price' => '2500.00',
            ]);

        $this->actingAs($reseller)
            ->getJson(route('orders.catalog.variants', [
                'market_id' => $this->marketByCode('lk')->id,
                'supplier_id' => $supplier->id,
                'product_id' => $unlocked->product_id,
            ]))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $unlocked->id,
                'price_locked' => false,
                'cost' => '1000.00',
                'company_commission' => '150.00',
                'suggested_price_min' => '2500.00',
                'suggested_price_max' => '3500.00',
                'customer_unit_price' => '2500.00',
            ]);
    }

    public function test_conflicting_identities_rejected(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);
        $countryId = $this->countryByIso('LK')->id;

        app(CustomerIdentityService::class)->resolveOrCreate([
            'display_name' => 'Cust A',
            'primary_country_id' => $countryId,
            'primary_phone' => '0701111001',
            'primary_phone_country_id' => $countryId,
        ]);
        app(CustomerIdentityService::class)->resolveOrCreate([
            'display_name' => 'Cust B',
            'primary_country_id' => $countryId,
            'primary_phone' => '0701111002',
            'primary_phone_country_id' => $countryId,
        ]);

        $before = Order::query()->count();

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'primary_phone' => '0701111001',
            'secondary_phone' => '0701111002',
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect(route('orders.create'))
            ->assertSessionHasErrors(['primary_phone']);

        $this->assertSame($before, Order::query()->count());
    }

    public function test_unauthorized_market_rejected(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view'], ['lk']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'market_id' => $this->marketByCode('my')->id,
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect(route('orders.create'))
            ->assertSessionHasErrors(['market_id']);
    }

    public function test_unauthorized_supplier_rejected(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $variant = $this->makeVariant($supplier);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect(route('orders.create'))
            ->assertSessionHasErrors(['supplier_id']);
    }

    public function test_product_market_mismatch_rejected(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view'], ['lk', 'my']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier, [], 'lk');

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'market_id' => $this->marketByCode('my')->id,
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect(route('orders.create'))
            ->assertSessionHasErrors();
    }

    public function test_mixed_supplier_rejected(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplierA = $this->makeSupplierUser();
        $supplierB = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplierA);
        $this->assignSupplier($reseller, $supplierB);
        $variantA = $this->makeVariant($supplierA);
        $variantB = $this->makeVariant($supplierB);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplierA->id,
            'items' => [
                ['product_variant_id' => $variantA->id, 'quantity' => 1],
                ['product_variant_id' => $variantB->id, 'quantity' => 1],
            ],
        ]))->assertRedirect(route('orders.create'))
            ->assertSessionHasErrors();
    }

    public function test_invalid_variant_rejected(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_variant_id' => 99999999, 'quantity' => 1],
            ],
        ]))->assertRedirect(route('orders.create'))
            ->assertSessionHasErrors();
    }

    public function test_zero_stock_variant_can_still_be_ordered(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect();

        $this->assertSame(1, Order::query()->where('reseller_company_id', $reseller->company_id)->count());
    }

    public function test_excessive_discount_rejected(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier, ['selling_price' => 100]);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'discount_amount' => 150,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect(route('orders.create'))
            ->assertSessionHasErrors(['discount_amount']);
    }

    public function test_duplicate_warning_then_explicit_override_succeeds(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);
        $phone = '070'.random_int(1000000, 9999999);

        $first = $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'primary_phone' => $phone,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]));
        $firstOrder = Order::query()->where('primary_phone_snapshot', $phone)->first();
        $this->assertNotNull($firstOrder);
        $first->assertRedirect(route('orders.show', $firstOrder));

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'primary_phone' => $phone,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect(route('orders.create'))
            ->assertSessionHas('duplicate_orders')
            ->assertSessionHasErrors(['duplicate']);

        $this->assertSame(1, Order::query()->where('primary_phone_snapshot', $phone)->count());

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'primary_phone' => $phone,
            'duplicate_warning_overridden' => true,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect();

        $this->assertSame(2, Order::query()->where('primary_phone_snapshot', $phone)->count());
    }

    public function test_cancelled_duplicate_excluded(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);
        $phone = '070'.random_int(1000000, 9999999);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'primary_phone' => $phone,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect();

        $existing = Order::query()->where('primary_phone_snapshot', $phone)->firstOrFail();
        $existing->forceFill(['status' => OrderStatus::CANCELLED])->save();

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'primary_phone' => $phone,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect();

        $this->assertSame(2, Order::query()->where('primary_phone_snapshot', $phone)->count());
    }

    public function test_after_hours_snapshot_present_when_applicable(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);

        // Force domain evaluation into after-hours via OrderService evaluated_at through app service
        // by creating during a known after-hours instant using OrderService directly is covered in domain tests.
        // Here we assert the catalog endpoint returns after-hours state and create still succeeds.
        $this->actingAs($reseller)
            ->getJson(route('orders.catalog.after-hours', [
                'market_id' => $this->marketByCode('lk')->id,
            ]))
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'after_hours',
                'after_hours_penalty_amount',
                'timezone',
                'local_time',
                'currency_code',
            ]]);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-07 22:30:00', 'Asia/Colombo')->utc());

        try {
            $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
                'supplier_id' => $supplier->id,
                'after_hours_warning_shown' => true,
                'items' => [
                    ['product_variant_id' => $variant->id, 'quantity' => 1],
                ],
            ]))->assertRedirect();

            $order = Order::query()->where('reseller_company_id', $reseller->company_id)->latest('id')->first();
            $this->assertTrue((bool) $order->after_hours);
            $this->assertTrue((bool) $order->after_hours_warning_shown);
            $this->assertGreaterThan(0, (float) $order->after_hours_penalty_amount);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_banned_primary_phone_blocks_create(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);
        $countryId = $this->countryByIso('LK')->id;

        $identity = app(CustomerIdentityService::class)->resolveOrCreate([
            'display_name' => 'Banned Cust',
            'primary_country_id' => $countryId,
            'primary_phone' => '0705555666',
            'primary_phone_country_id' => $countryId,
        ]);
        app(CustomerBanService::class)->ban($identity['customer'], $reseller->id, $reseller->company_id, 'Fraud');

        $before = Order::query()->count();

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'primary_phone' => '0705555666',
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect(route('orders.create'))
            ->assertSessionHasErrors(['primary_phone']);

        $this->assertSame($before, Order::query()->count());
    }

    public function test_banned_secondary_phone_blocks_create(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);
        $countryId = $this->countryByIso('LK')->id;

        $identity = app(CustomerIdentityService::class)->resolveOrCreate([
            'display_name' => 'Banned Secondary',
            'primary_country_id' => $countryId,
            'primary_phone' => '0705555777',
            'primary_phone_country_id' => $countryId,
        ]);
        app(CustomerBanService::class)->ban($identity['customer'], $reseller->id, $reseller->company_id, 'Fraud');

        $before = Order::query()->count();

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'primary_phone' => '070'.random_int(1000000, 9999999),
            'secondary_phone' => '0705555777',
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect(route('orders.create'))
            ->assertSessionHasErrors(['secondary_phone']);

        $this->assertSame($before, Order::query()->count());
    }

    public function test_unbanned_phones_allow_creation(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);
        $phone = '070'.random_int(1000000, 9999999);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'primary_phone' => $phone,
            'secondary_phone' => '071'.random_int(1000000, 9999999),
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect();

        $order = Order::query()->where('primary_phone_snapshot', $phone)->first();
        $this->assertNotNull($order);
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame(OrderType::NEW, $order->order_type);
    }

    public function test_same_variant_quantities_merge_before_create(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier, ['selling_price' => 100.00]);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 2],
                ['product_variant_id' => $variant->id, 'quantity' => 3],
            ],
        ]))->assertRedirect();

        $order = Order::query()->where('reseller_company_id', $reseller->company_id)->latest('id')->first();
        $this->assertCount(1, $order->items);
        $this->assertSame(5, (int) $order->items->first()->quantity);
        $this->assertSame('500.00', (string) $order->items_subtotal);
    }

    public function test_confirm_order_creates_new_pending_not_confirmed(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'intent' => 'confirm',
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect();

        $order = Order::query()->where('reseller_company_id', $reseller->company_id)->latest('id')->first();
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame(OrderType::NEW, $order->order_type);
        $this->assertNull($order->confirmed_at);
        $this->assertNull($order->shipment);
        $this->assertSame(0, Shipment::query()->where('order_id', $order->id)->count());
    }

    public function test_send_to_call_center_creates_pending_and_preserves_assignment(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'intent' => 'send_to_call_center',
            'assignment_target' => 'pool',
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect();

        $order = Order::query()->where('reseller_company_id', $reseller->company_id)->latest('id')->first();
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertTrue((bool) $order->available_in_pool);
        $this->assertNull($order->cca_id);
    }

    public function test_address_persists_single_line_without_destroying_line2_column(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'address_line1' => '42 Flower Road, Colombo',
            'address_line2' => '',
            'city_name' => '',
            'district_name' => '',
            'full_address_text' => '42 Flower Road, Colombo',
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect();

        $order = Order::query()->where('reseller_company_id', $reseller->company_id)->latest('id')->with('address')->first();
        $this->assertSame('42 Flower Road, Colombo', $order->address->line1);
        $this->assertNull($order->address->line2);
        $this->assertSame('', $order->address->city_name);
        $this->assertSame('42 Flower Road, Colombo', $order->address->full_address_text);
    }

    public function test_courier_draft_persists_when_supplied_without_shipment(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier, ['weight' => 1.0, 'selling_price' => 250.00]);
        $setup = $this->makeCourierSetup($supplier);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'courier_id' => $setup['courier']->id,
            'district_name' => 'Colombo',
            'city_name' => 'Colombo 03',
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect();

        $order = Order::query()->where('reseller_company_id', $reseller->company_id)->latest('id')->first();
        $this->assertSame((int) $setup['courier']->id, (int) $order->draft_courier_id);
        $this->assertSame((int) $setup['service']->id, (int) $order->draft_courier_service_id);
        $this->assertSame((int) $setup['city']->id, (int) $order->draft_courier_city_id);
        $this->assertSame('600.00', (string) $order->courier_fee_amount);
        $this->assertSame('850.00', (string) $order->customer_payable_amount);
        $this->assertNull($order->shipment);
        $this->assertSame(0, Shipment::query()->where('order_id', $order->id)->count());
    }

    public function test_courier_remains_optional_during_creation(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'city_name' => null,
            'district_name' => null,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect();

        $order = Order::query()->where('reseller_company_id', $reseller->company_id)->latest('id')->first();
        $this->assertNull($order->draft_courier_id);
        $this->assertNull($order->draft_courier_service_id);
        $this->assertNull($order->draft_courier_city_id);
        $this->assertSame('0.00', (string) $order->courier_fee_amount);
    }

    public function test_existing_orders_receive_new_order_type_default_compatibility(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect();

        $order = Order::query()->where('reseller_company_id', $reseller->company_id)->latest('id')->first();
        $this->assertSame(OrderType::NEW, $order->order_type);
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'order_type' => 'NEW',
        ]);
    }

    public function test_banned_customer_warning_lookup_still_surfaces_ban_state(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $countryId = $this->countryByIso('LK')->id;
        $identity = app(CustomerIdentityService::class)->resolveOrCreate([
            'display_name' => 'Banned Cust',
            'primary_country_id' => $countryId,
            'primary_phone' => '0705555666',
            'primary_phone_country_id' => $countryId,
        ]);
        app(CustomerBanService::class)->ban($identity['customer'], $reseller->id, $reseller->company_id, 'Fraud');

        $this->actingAs($reseller)
            ->getJson(route('orders.catalog.customer-lookup', [
                'phone' => '0705555666',
                'country_id' => $countryId,
            ]))
            ->assertOk()
            ->assertJsonPath('data.is_banned', true)
            ->assertJsonPath('data.found', true);
    }

    public function test_cca_id_submitted_from_browser_is_ignored(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);

        $this->actingAs($reseller)->post(route('orders.store'), $this->minimalPayload([
            'supplier_id' => $supplier->id,
            'cca_id' => 999999,
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
        ]))->assertRedirect();

        $order = Order::query()->where('reseller_company_id', $reseller->company_id)->latest('id')->first();
        $this->assertNull($order->cca_id);
    }

    public function test_catalog_endpoints_scope_products_to_assigned_supplier_and_market(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $other = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);
        $this->makeVariant($other);

        $this->actingAs($reseller)
            ->getJson(route('orders.catalog.suppliers', [
                'market_id' => $this->marketByCode('lk')->id,
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $supplier->id])
            ->assertJsonMissing(['id' => $other->id]);

        $this->actingAs($reseller)
            ->getJson(route('orders.catalog.products', [
                'market_id' => $this->marketByCode('lk')->id,
                'supplier_id' => $supplier->id,
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $variant->product_id]);

        $this->actingAs($reseller)
            ->getJson(route('orders.catalog.variants', [
                'market_id' => $this->marketByCode('lk')->id,
                'supplier_id' => $supplier->id,
                'product_id' => $variant->product_id,
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $variant->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minimalPayload(array $overrides = []): array
    {
        return array_merge([
            'market_id' => $this->marketByCode('lk')->id,
            'supplier_id' => 0,
            'customer_name' => 'Manual Customer',
            'primary_phone' => '070'.random_int(1000000, 9999999),
            'address_line1' => '12 Test Street',
            'city_name' => 'Colombo',
            'district_name' => 'Colombo',
            'items' => [],
            'discount_amount' => 0,
            'intent' => 'send_to_call_center',
            'assignment_target' => 'unassigned',
        ], $overrides);
    }

    /**
     * @return array{courier: Courier, service: CourierService, city: CourierCity}
     */
    private function makeCourierSetup(User $supplier): array
    {
        $market = $this->marketByCode('lk');

        $courier = Courier::query()->create([
            'code' => 'DOM'.strtoupper(substr(uniqid(), -4)),
            'name' => 'Domestic Courier',
            'is_active' => true,
        ]);

        $service = CourierService::query()->create([
            'courier_id' => $courier->id,
            'code' => 'COD',
            'name' => 'Cash on Delivery',
            'external_service_id' => 'ext-cod',
            'is_active' => true,
        ]);

        $city = CourierCity::query()->create([
            'courier_id' => $courier->id,
            'district_name' => 'Colombo',
            'city_name' => 'Colombo 03',
            'external_city_code' => 'CMB03-'.Str::lower(Str::random(4)),
            'external_district_code' => 'COL',
            'is_active' => true,
        ]);

        CourierMarketPricing::query()->create([
            'courier_id' => $courier->id,
            'market_id' => $market->id,
            'currency_id' => $market->currency_id,
            'first_kg_fee' => 600.00,
            'additional_kg_fee' => 100.00,
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

        return compact('courier', 'service', 'city');
    }

    /**
     * @param  list<string>  $permissionSlugs
     * @param  list<string>  $marketCodes
     */
    private function makeResellerWithPermission(array $permissionSlugs, array $marketCodes = ['lk']): User
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
        $this->configureResellerCompany($company, $marketCodes);

        $role = Role::query()->create([
            'uuid' => (string) Str::uuid(),
            'portal_id' => $portal->id,
            'company_id' => null,
            'slug' => 'owner-order-'.Str::lower(Str::random(6)),
            'name' => 'Owner Order Test',
            'description' => 'Owner Order Test',
            'is_system' => false,
        ]);

        $permissionIds = [];
        foreach ($permissionSlugs as $slug) {
            $permission = Permission::query()->firstOrCreate(
                [
                    'portal_id' => $portal->id,
                    'slug' => $slug,
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'module' => 'Orders',
                    'group' => 'Orders',
                    'name' => $slug,
                    'description' => null,
                    'sort_order' => 10,
                ]
            );
            $permissionIds[] = $permission->id;
        }

        $role->permissions()->sync($permissionIds);
        $user->forceFill(['role_id' => $role->id])->save();

        return $user->fresh(['company', 'role']);
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

    private function assignSupplier(User $reseller, User $supplier): void
    {
        ResellerSupplierAssignment::query()->create([
            'reseller_id' => $reseller->id,
            'supplier_id' => $supplier->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $variantOverrides
     */
    private function makeVariant(
        User $supplier,
        array $variantOverrides = [],
        string $marketCode = 'lk',
        bool $priceLocked = true,
    ): ProductVariant {
        $category = ProductCategory::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'General',
            'slug' => 'general-'.Str::lower(Str::random(6)),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'market_id' => $this->marketByCode($marketCode)->id,
            'name' => 'Product '.Str::upper(Str::random(5)),
            'slug' => 'product-'.Str::lower(Str::random(8)),
            'status' => ProductStatus::ACTIVE->value,
            'system_visible' => true,
            'web_visible' => true,
            'price_locked' => $priceLocked,
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ]);

        return ProductVariant::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'name' => 'Default',
            'barcode' => 'BC'.Str::upper(Str::random(10)),
            'cost' => 100.00,
            'selling_price' => 250.00,
            'weight' => 0.500,
            'company_commission' => 150.00,
            'is_active' => true,
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ], $variantOverrides));
    }
}
