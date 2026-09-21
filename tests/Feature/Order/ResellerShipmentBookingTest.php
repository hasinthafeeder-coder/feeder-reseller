<?php

namespace Tests\Feature\Order;

use Feeder\Core\Contracts\Courier\CourierBookingAdapter;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\OrderSource;
use Feeder\Core\Enums\OrderStatus;
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
use Feeder\Core\Models\CourierState;
use Feeder\Core\Models\Customer;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\OrderAddress;
use Feeder\Core\Models\OrderItem;
use Feeder\Core\Models\OrderStatusHistory;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\ProductCategory;
use Feeder\Core\Models\ProductVariant;
use Feeder\Core\Models\ResellerSupplierAssignment;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\Shipment;
use Feeder\Core\Models\ShipmentEvent;
use Feeder\Core\Models\SupplierCourierAccount;
use Feeder\Core\Models\User;
use Feeder\Core\Models\UserProfile;
use Feeder\Core\Services\Courier\CourierBookingAdapterResolver;
use Feeder\Core\Services\Courier\Curfox\RoyalBookingAdapter;
use Feeder\Core\Services\Order\CallCenterAgentEligibilityService;
use Feeder\Core\Services\Order\OrderService;
use Feeder\Core\Services\Order\ShipmentBookingService;
use Feeder\Core\Services\UuidService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class ResellerShipmentBookingTest extends TestCase
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

    public function test_reseller_can_book_own_order_and_persists_tracking_fee_event(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.shipment.book']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier), [
            'items_subtotal' => 500,
            'discount_amount' => 50,
            'courier_fee_amount' => 0,
            'customer_payable_amount' => 450,
            'total_weight' => 1.5,
        ]);
        $setup = $this->makeCourierSetup($order);
        $this->registerSuccessfulAdapter($setup['courier']->code, 'TRK-RESELLER-1');

        $this->actingAs($actor)
            ->post(route('orders.shipment.book', $order), [
                'courier_id' => $setup['courier']->id,
                'courier_service_id' => $setup['service']->id,
                'courier_city_id' => $setup['city']->id,
            ])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('success');

        $order = $order->fresh(['shipment.events']);
        $this->assertNotNull($order->shipment);
        $this->assertSame('TRK-RESELLER-1', $order->shipment->tracking_number);
        $this->assertSame(ShipmentStatus::BOOKED, $order->shipment->status);
        $this->assertSame('700.00', (string) $order->courier_fee_amount);
        $this->assertSame('1150.00', (string) $order->customer_payable_amount);
        $this->assertSame(1, ShipmentEvent::query()->where('shipment_id', $order->shipment->id)->count());

        $this->actingAs($actor)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('TRK-RESELLER-1')
            ->assertSee('Booked')
            ->assertDontSee('Book Shipment');
    }

    public function test_reseller_cannot_book_another_company_order(): void
    {
        $resellerA = $this->makeResellerWithPermission(['orders.view', 'orders.shipment.book']);
        $resellerB = $this->makeResellerWithPermission(['orders.view', 'orders.shipment.book']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($resellerA, $supplier);
        $this->assignSupplier($resellerB, $supplier);
        $orderB = $this->makeOrder($resellerB, $supplier, $this->makeVariant($supplier));
        $setup = $this->makeCourierSetup($orderB);
        $this->registerSuccessfulAdapter($setup['courier']->code, 'TRK-X');

        $this->actingAs($resellerA)
            ->post(route('orders.shipment.book', $orderB), [
                'courier_id' => $setup['courier']->id,
                'courier_service_id' => $setup['service']->id,
                'courier_city_id' => $setup['city']->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('shipments', ['order_id' => $orderB->id]);
    }

    public function test_cca_can_book_within_assigned_company_only(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.view', 'orders.shipment.book']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $supplier);
        $order = $this->makeOrder($owner, $supplier, $this->makeVariant($supplier), [
            'items_subtotal' => 250,
            'customer_payable_amount' => 250,
            'total_weight' => 0.5,
        ]);
        $setup = $this->makeCourierSetup($order);
        $this->registerSuccessfulAdapter($setup['courier']->code, 'TRK-CCA-1');

        $cca = $this->makeCcaWithPermission($owner->company, ['orders.view', 'orders.shipment.book']);

        $this->actingAs($cca)
            ->post(route('orders.shipment.book', $order), [
                'courier_id' => $setup['courier']->id,
                'courier_service_id' => $setup['service']->id,
                'courier_city_id' => $setup['city']->id,
            ])
            ->assertRedirect(route('orders.show', $order));

        $this->assertDatabaseHas('shipments', [
            'order_id' => $order->id,
            'tracking_number' => 'TRK-CCA-1',
            'booked_by' => $cca->id,
        ]);

        $otherOwner = $this->makeResellerWithPermission(['orders.view', 'orders.shipment.book']);
        $this->assignSupplier($otherOwner, $supplier);
        $otherOrder = $this->makeOrder($otherOwner, $supplier, $this->makeVariant($supplier));
        $otherSetup = $this->makeCourierSetup($otherOrder);
        $this->registerSuccessfulAdapter($otherSetup['courier']->code, 'TRK-CCA-X');

        $this->actingAs($cca)
            ->post(route('orders.shipment.book', $otherOrder), [
                'courier_id' => $otherSetup['courier']->id,
                'courier_service_id' => $otherSetup['service']->id,
                'courier_city_id' => $otherSetup['city']->id,
            ])
            ->assertNotFound();
    }

    public function test_unauthorized_user_cannot_book(): void
    {
        $viewer = $this->makeResellerWithPermission(['orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($viewer, $supplier);
        $order = $this->makeOrder($viewer, $supplier, $this->makeVariant($supplier));
        $setup = $this->makeCourierSetup($order);
        $this->registerSuccessfulAdapter($setup['courier']->code, 'TRK-FORBID');

        $this->actingAs($viewer)
            ->post(route('orders.shipment.book', $order), [
                'courier_id' => $setup['courier']->id,
                'courier_service_id' => $setup['service']->id,
                'courier_city_id' => $setup['city']->id,
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->get(route('orders.couriers', $order))
            ->assertForbidden();
    }

    public function test_courier_lookup_endpoints_are_local_and_scoped(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.shipment.book']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier));
        $setup = $this->makeCourierSetup($order);

        $foreignCourier = Courier::query()->create([
            'code' => 'FRN'.strtoupper(substr(uniqid(), -4)),
            'name' => 'Foreign Courier',
            'is_active' => true,
        ]);
        $foreignService = CourierService::query()->create([
            'courier_id' => $foreignCourier->id,
            'code' => 'STD',
            'name' => 'Standard',
            'is_active' => true,
        ]);
        $foreignCity = CourierCity::query()->create([
            'courier_id' => $foreignCourier->id,
            'district_name' => 'Galle',
            'city_name' => 'Galle Town',
            'external_city_code' => 'GAL01',
            'is_active' => true,
        ]);

        $this->actingAs($actor)
            ->getJson(route('orders.couriers', $order))
            ->assertOk()
            ->assertJsonFragment(['id' => $setup['courier']->id])
            ->assertJsonMissing(['id' => $foreignCourier->id]);

        $this->actingAs($actor)
            ->getJson(route('orders.courier-services', $order).'?courier_id='.$setup['courier']->id)
            ->assertOk()
            ->assertJsonFragment(['id' => $setup['service']->id]);

        $this->actingAs($actor)
            ->getJson(route('orders.courier-districts', $order).'?courier_service_id='.$setup['service']->id)
            ->assertOk()
            ->assertJsonFragment(['district' => 'Colombo']);

        $this->actingAs($actor)
            ->getJson(route('orders.courier-cities', $order).'?courier_service_id='.$setup['service']->id.'&district=Colombo')
            ->assertOk()
            ->assertJsonFragment(['id' => $setup['city']->id])
            ->assertJsonMissing(['id' => $foreignCity->id]);

        $this->actingAs($actor)
            ->getJson(route('orders.courier-districts', $order).'?courier_service_id='.$foreignService->id)
            ->assertStatus(422);

        $this->registerSuccessfulAdapter($setup['courier']->code, 'TRK-BAD-CITY');
        $this->actingAs($actor)
            ->post(route('orders.shipment.book', $order), [
                'courier_id' => $setup['courier']->id,
                'courier_service_id' => $setup['service']->id,
                'courier_city_id' => $foreignCity->id,
            ])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('courier_city_id');
    }

    public function test_location_endpoints_are_courier_and_state_scoped(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.shipment.book', 'orders.create']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier));
        $setup = $this->makeCourierSetup($order);

        $royalState = CourierState::query()->create([
            'courier_id' => $setup['courier']->id,
            'external_id' => '1',
            'name' => 'Colombo',
            'is_active' => true,
        ]);
        $gampahaState = CourierState::query()->create([
            'courier_id' => $setup['courier']->id,
            'external_id' => '3',
            'name' => 'Gampaha',
            'is_active' => true,
        ]);

        $setup['city']->courier_state_id = $royalState->id;
        $setup['city']->district_name = 'Colombo';
        $setup['city']->city_name = 'Dehiwala';
        $setup['city']->save();

        $negombo = CourierCity::query()->create([
            'courier_id' => $setup['courier']->id,
            'courier_state_id' => $gampahaState->id,
            'district_name' => 'Gampaha',
            'city_name' => 'Negombo',
            'external_city_code' => 'NEG-'.Str::lower(Str::random(4)),
            'is_active' => true,
        ]);

        $otherCourier = Courier::query()->create([
            'code' => 'OTH'.strtoupper(substr(uniqid(), -4)),
            'name' => 'Other Courier',
            'is_active' => true,
        ]);
        $otherState = CourierState::query()->create([
            'courier_id' => $otherCourier->id,
            'external_id' => '1',
            'name' => 'Colombo',
            'is_active' => true,
        ]);
        $otherCity = CourierCity::query()->create([
            'courier_id' => $otherCourier->id,
            'courier_state_id' => $otherState->id,
            'district_name' => 'Colombo',
            'city_name' => 'Dehiwala',
            'external_city_code' => 'OTH-DEH',
            'is_active' => true,
        ]);

        $districts = $this->actingAs($actor)
            ->getJson(route('orders.courier-districts', $order).'?courier_service_id='.$setup['service']->id)
            ->assertOk()
            ->json('data');

        $districtNames = array_column($districts, 'district');
        $this->assertContains('Colombo', $districtNames);
        $this->assertContains('Gampaha', $districtNames);
        $this->assertNotEmpty(array_filter($districts, fn ($row) => (int) ($row['id'] ?? 0) === (int) $royalState->id));

        $colomboCities = $this->actingAs($actor)
            ->getJson(
                route('orders.courier-cities', $order)
                .'?courier_service_id='.$setup['service']->id
                .'&courier_state_id='.$royalState->id
            )
            ->assertOk()
            ->json('data');

        $cityIds = array_column($colomboCities, 'id');
        $this->assertContains((int) $setup['city']->id, $cityIds);
        $this->assertNotContains((int) $negombo->id, $cityIds);
        $this->assertNotContains((int) $otherCity->id, $cityIds);

        $catalogCities = $this->actingAs($actor)
            ->getJson(route('orders.catalog.courier-cities').'?'.http_build_query([
                'market_id' => $order->market_id,
                'supplier_id' => $order->supplier_id,
                'courier_id' => $setup['courier']->id,
                'courier_state_id' => $royalState->id,
            ]))
            ->assertOk()
            ->json('data');

        $catalogIds = array_column($catalogCities, 'id');
        $this->assertContains((int) $setup['city']->id, $catalogIds);
        $this->assertNotContains((int) $otherCity->id, $catalogIds);

        $this->actingAs($actor)
            ->getJson(
                route('orders.courier-cities', $order)
                .'?courier_service_id='.$setup['service']->id
                .'&courier_state_id='.$otherState->id
            )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['courier_state_id']);
    }

    public function test_missing_supplier_account_prevents_api_and_wrong_account_rejected(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.shipment.book']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier));
        $setup = $this->makeCourierSetup($order, withAccount: false);

        $probe = new \stdClass;
        $probe->called = false;
        $this->registerAdapter($setup['courier']->code, new class($probe) implements CourierBookingAdapter
        {
            public function __construct(private readonly object $probe)
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
                $this->probe->called = true;

                return ['tracking_number' => 'SHOULD-NOT'];
            }
        });

        $this->actingAs($actor)
            ->post(route('orders.shipment.book', $order), [
                'courier_id' => $setup['courier']->id,
                'courier_service_id' => $setup['service']->id,
                'courier_city_id' => $setup['city']->id,
            ])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('courier_id');

        $this->assertFalse($probe->called);
        $this->assertDatabaseMissing('shipments', ['order_id' => $order->id]);
    }

    public function test_repeated_booking_is_rejected_and_locks_mutations(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.shipment.book']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier), [
            'items_subtotal' => 250,
            'customer_payable_amount' => 250,
            'total_weight' => 1.0,
        ]);
        $setup = $this->makeCourierSetup($order);
        $this->registerSuccessfulAdapter($setup['courier']->code, 'TRK-ONCE');

        $this->actingAs($actor)
            ->post(route('orders.shipment.book', $order), [
                'courier_id' => $setup['courier']->id,
                'courier_service_id' => $setup['service']->id,
                'courier_city_id' => $setup['city']->id,
            ])
            ->assertRedirect(route('orders.show', $order));

        $this->actingAs($actor)
            ->post(route('orders.shipment.book', $order), [
                'courier_id' => $setup['courier']->id,
                'courier_service_id' => $setup['service']->id,
                'courier_city_id' => $setup['city']->id,
            ])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('order_id');

        $this->assertSame(1, Shipment::query()->where('order_id', $order->id)->count());

        $order = $order->fresh(['items', 'shipment']);
        $booking = app(ShipmentBookingService::class);
        $orderService = app(OrderService::class);
        $itemId = (int) $order->items->first()->id;
        $otherVariant = $this->makeVariant($supplier);

        $rejections = 0;
        $attempts = [
            fn () => $orderService->replaceItemVariant($order, $itemId, $otherVariant->id, null, (int) $actor->company_id),
            fn () => $orderService->updateItemQuantity($order, $itemId, 9, null, (int) $actor->company_id),
            fn () => $booking->updateCourierSelection(
                $order,
                $setup['courier']->id,
                $setup['service']->id,
                $setup['city']->id,
                null,
                (int) $actor->company_id,
            ),
            fn () => $booking->updateTrackingNumber($order, 'TRK-MUTATED', null, (int) $actor->company_id),
        ];

        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('Expected shipment lock rejection.');
            } catch (ValidationException) {
                $rejections++;
            }
        }

        $this->assertSame(count($attempts), $rejections);
        $this->assertSame('TRK-ONCE', $order->fresh('shipment')->shipment->tracking_number);
    }

    public function test_courier_api_rejection_does_not_create_shipment_or_leak_credentials(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.shipment.book']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier));
        $setup = $this->makeCourierSetup($order);

        $this->registerAdapter($setup['courier']->code, new class implements CourierBookingAdapter
        {
            public function book(
                Order $order,
                Courier $courier,
                CourierService $service,
                CourierCity $city,
                ?SupplierCourierAccount $account,
                float $weightKg,
                float $courierFee,
            ): array {
                throw ValidationException::withMessages([
                    'booking' => ['Courier rejected the booking request.'],
                ]);
            }
        });

        $response = $this->actingAs($actor)
            ->from(route('orders.show', $order))
            ->post(route('orders.shipment.book', $order), [
                'courier_id' => $setup['courier']->id,
                'courier_service_id' => $setup['service']->id,
                'courier_city_id' => $setup['city']->id,
            ]);

        $response->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('booking');

        $sessionErrors = session('errors');
        $flat = $sessionErrors ? $sessionErrors->all() : [];
        $joined = implode(' ', $flat);
        $this->assertStringNotContainsString('secret-api-key', $joined);
        $this->assertStringNotContainsString('credentials', strtolower($joined));
        $this->assertDatabaseMissing('shipments', ['order_id' => $order->id]);
    }

    public function test_fee_preview_uses_calculator_and_payable_formula(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.shipment.book']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier), [
            'items_subtotal' => 1000,
            'discount_amount' => 100,
            'courier_fee_amount' => 0,
            'customer_payable_amount' => 900,
            'total_weight' => 2.2,
        ]);
        $setup = $this->makeCourierSetup($order);

        $this->actingAs($actor)
            ->getJson(route('orders.courier-fee-preview', $order).'?courier_id='.$setup['courier']->id)
            ->assertOk()
            ->assertJsonPath('data.courier_fee_amount', 800)
            ->assertJsonPath('data.customer_payable_amount', 1700);
    }

    public function test_show_page_includes_shipment_panel(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.shipment.book']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier));
        $this->makeCourierSetup($order);

        $this->actingAs($actor)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Shipment / Courier')
            ->assertSee('Book Shipment');
    }

    public function test_editable_order_form_exposes_assign_courier_controls(): void
    {
        $actor = $this->makeResellerWithPermission([
            'orders.view',
            'orders.update',
            'orders.shipment.book',
        ]);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier));
        $this->makeCourierSetup($order);

        $this->actingAs($actor)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Assign Courier')
            ->assertSee('id="assignCourierDebug"', false)
            ->assertDontSee('Book Shipment');
    }

    public function test_json_assign_courier_books_without_explicit_service_id(): void
    {
        $actor = $this->makeResellerWithPermission([
            'orders.view',
            'orders.update',
            'orders.shipment.book',
        ]);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier), [
            'items_subtotal' => 500,
            'discount_amount' => 0,
            'courier_fee_amount' => 0,
            'customer_payable_amount' => 500,
            'total_weight' => 1.0,
        ]);
        $setup = $this->makeCourierSetup($order);
        $this->registerSuccessfulAdapter($setup['courier']->code, 'WB-ASSIGN-1');

        $this->actingAs($actor)
            ->postJson(route('orders.shipment.book', $order), [
                'courier_id' => $setup['courier']->id,
                'courier_city_id' => $setup['city']->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.waybill', 'WB-ASSIGN-1')
            ->assertJsonPath('data.courier.name', $setup['courier']->name);

        $order = $order->fresh(['shipment.courier']);
        $this->assertNotNull($order->shipment);
        $this->assertSame('WB-ASSIGN-1', $order->shipment->tracking_number);
        $this->assertSame((int) $setup['courier']->id, (int) $order->shipment->courier_id);
        $this->assertSame((int) $setup['service']->id, (int) $order->shipment->courier_service_id);
    }

    public function test_json_assign_courier_requires_courier_and_city(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.shipment.book']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier));
        $setup = $this->makeCourierSetup($order);

        $probe = new \stdClass;
        $probe->called = false;
        $this->registerAdapter($setup['courier']->code, new class($probe) implements CourierBookingAdapter
        {
            public function __construct(private readonly object $probe)
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
                $this->probe->called = true;

                return ['tracking_number' => 'WB-SHOULD-NOT'];
            }
        });

        $this->actingAs($actor)
            ->postJson(route('orders.shipment.book', $order), [
                'courier_city_id' => $setup['city']->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['courier_id']);

        $this->actingAs($actor)
            ->postJson(route('orders.shipment.book', $order), [
                'courier_id' => $setup['courier']->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['courier_city_id']);

        $this->assertFalse($probe->called);
        $this->assertDatabaseMissing('shipments', ['order_id' => $order->id]);
    }

    public function test_json_assign_courier_rejects_ineligible_courier(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.shipment.book']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier));
        $this->makeCourierSetup($order);

        $foreign = Courier::query()->create([
            'code' => 'FRN'.strtoupper(substr(uniqid(), -4)),
            'name' => 'Foreign Courier',
            'is_active' => true,
        ]);
        $foreignService = CourierService::query()->create([
            'courier_id' => $foreign->id,
            'code' => 'COD',
            'name' => 'COD',
            'external_service_id' => 'ext-frn',
            'is_active' => true,
        ]);
        $foreignCity = CourierCity::query()->create([
            'courier_id' => $foreign->id,
            'district_name' => 'Gampaha',
            'city_name' => 'Negombo',
            'external_city_code' => 'NEG-'.Str::lower(Str::random(4)),
            'external_district_code' => 'GAM',
            'is_active' => true,
        ]);

        $probe = new \stdClass;
        $probe->called = false;
        $this->registerAdapter($foreign->code, new class($probe) implements CourierBookingAdapter
        {
            public function __construct(private readonly object $probe)
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
                $this->probe->called = true;

                return ['tracking_number' => 'WB-FOREIGN'];
            }
        });

        $this->actingAs($actor)
            ->postJson(route('orders.shipment.book', $order), [
                'courier_id' => $foreign->id,
                'courier_service_id' => $foreignService->id,
                'courier_city_id' => $foreignCity->id,
            ])
            ->assertStatus(422);

        $this->assertFalse($probe->called);
        $this->assertDatabaseMissing('shipments', ['order_id' => $order->id]);
    }

    public function test_json_assign_courier_adapter_failure_persists_no_waybill(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.shipment.book']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier));
        $setup = $this->makeCourierSetup($order);

        $this->registerAdapter($setup['courier']->code, new class implements CourierBookingAdapter
        {
            public function book(
                Order $order,
                Courier $courier,
                CourierService $service,
                CourierCity $city,
                ?SupplierCourierAccount $account,
                float $weightKg,
                float $courierFee,
            ): array {
                throw ValidationException::withMessages([
                    'booking' => ['Curfox rejected the booking.'],
                ]);
            }
        });

        $this->actingAs($actor)
            ->postJson(route('orders.shipment.book', $order), [
                'courier_id' => $setup['courier']->id,
                'courier_city_id' => $setup['city']->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['booking']);

        $this->assertDatabaseMissing('shipments', ['order_id' => $order->id]);
        $this->assertNull($order->fresh('shipment')->shipment);
    }

    public function test_json_assign_courier_returns_royal_422_response_without_persisting_shipment(): void
    {
        $handler = new TestHandler();
        Log::getFacadeRoot()->pushHandler($handler);

        $context = $this->prepareRoyalAssignCourier();

        Http::fake([
            '*/api/public/merchant/order/single' => Http::response([
                'success' => false,
                'message' => 'Invalid city',
                'errors' => [
                    'city' => ['City not found'],
                ],
            ], 422),
        ]);

        $this->actingAs($context['actor'])
            ->postJson(route('orders.shipment.book', $context['order']), [
                'courier_id' => $context['courier']->id,
                'courier_city_id' => $context['city']->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Domestic Courier booking failed')
            ->assertJsonPath('debug.provider', 'ROYAL')
            ->assertJsonPath('debug.failure_type', 'http_error')
            ->assertJsonPath('debug.http_status', 422)
            ->assertJsonPath('debug.response.message', 'Invalid city')
            ->assertJsonPath('debug.response.errors.city.0', 'City not found')
            ->assertJsonPath('debug.request.order_data.0.destination_city_name', 'Colombo 03')
            ->assertJsonPath('debug.request.order_data.0.destination_state_name', 'Colombo')
            ->assertJsonPath('debug.request.order_data.0.customer_phone', '077****123')
            ->assertJsonPath('debug.request.general_data.merchant_business_id', '2');

        $this->assertDatabaseMissing('shipments', ['order_id' => $context['order']->id]);
        $this->assertNull($context['order']->fresh('shipment')->shipment);

        $logged = false;
        foreach ($handler->getRecords() as $record) {
            $message = is_array($record) ? ($record['message'] ?? '') : $record->message;
            $contextData = is_array($record) ? ($record['context'] ?? []) : $record->context;
            if ($message !== 'royal_booking_failed') {
                continue;
            }

            $logged = ($contextData['order_id'] ?? null) === $context['order']->id
                && ($contextData['supplier_id'] ?? null) === $context['order']->supplier_id
                && ($contextData['courier_account_id'] ?? null) !== null
                && ($contextData['http_status'] ?? null) === 422
                && ($contextData['response']['message'] ?? null) === 'Invalid city'
                && ! array_key_exists('password', $contextData)
                && ! array_key_exists('token', $contextData);
        }

        $this->assertTrue($logged, 'Expected royal_booking_failed log with the provider response.');
    }

    public function test_json_assign_courier_returns_royal_500_response(): void
    {
        $context = $this->prepareRoyalAssignCourier();

        Http::fake([
            '*/api/public/merchant/order/single' => Http::response([
                'message' => 'Internal server error',
                'error_code' => 'E500',
            ], 500),
        ]);

        $this->actingAs($context['actor'])
            ->postJson(route('orders.shipment.book', $context['order']), [
                'courier_id' => $context['courier']->id,
                'courier_city_id' => $context['city']->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('debug.http_status', 500)
            ->assertJsonPath('debug.failure_type', 'http_error')
            ->assertJsonPath('debug.response.message', 'Internal server error')
            ->assertJsonPath('debug.response.error_code', 'E500');

        $this->assertDatabaseMissing('shipments', ['order_id' => $context['order']->id]);
    }

    public function test_json_assign_courier_returns_royal_non_json_body(): void
    {
        $context = $this->prepareRoyalAssignCourier();

        Http::fake([
            '*/api/public/merchant/order/single' => Http::response('Upstream said: not json', 200, [
                'Content-Type' => 'text/plain',
            ]),
        ]);

        $this->actingAs($context['actor'])
            ->postJson(route('orders.shipment.book', $context['order']), [
                'courier_id' => $context['courier']->id,
                'courier_city_id' => $context['city']->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('debug.failure_type', 'invalid_json')
            ->assertJsonPath('debug.http_status', 200)
            ->assertJsonPath('debug.response', 'Upstream said: not json');

        $this->assertDatabaseMissing('shipments', ['order_id' => $context['order']->id]);
    }

    public function test_json_assign_courier_returns_royal_connection_timeout(): void
    {
        $context = $this->prepareRoyalAssignCourier();

        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 60000 milliseconds');
        });

        $this->actingAs($context['actor'])
            ->postJson(route('orders.shipment.book', $context['order']), [
                'courier_id' => $context['courier']->id,
                'courier_city_id' => $context['city']->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('debug.failure_type', 'connection')
            ->assertJsonPath('debug.http_status', null)
            ->assertJsonPath('debug.exception.message', 'Connection timeout')
            ->assertJsonPath('errors.booking.0', 'Connection timeout');

        $this->assertDatabaseMissing('shipments', ['order_id' => $context['order']->id]);
    }

    public function test_json_assign_courier_royal_success_still_persists_waybill(): void
    {
        $context = $this->prepareRoyalAssignCourier();

        Http::fake([
            '*/api/public/merchant/order/single' => Http::response([
                'message' => 'Orders Created Successfully',
                'data' => ['AU145'],
            ], 200),
        ]);

        $this->actingAs($context['actor'])
            ->postJson(route('orders.shipment.book', $context['order']), [
                'courier_id' => $context['courier']->id,
                'courier_city_id' => $context['city']->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.waybill', 'AU145')
            ->assertJsonMissingPath('debug');

        $order = $context['order']->fresh('shipment');
        $this->assertNotNull($order->shipment);
        $this->assertSame('AU145', $order->shipment->tracking_number);
    }

    public function test_json_assign_courier_royal_http_200_rejection_does_not_persist_waybill(): void
    {
        $context = $this->prepareRoyalAssignCourier();

        Http::fake([
            '*/api/public/merchant/order/single' => Http::response([
                'success' => false,
                'message' => 'Invalid city',
                'errors' => [
                    'city' => ['The selected city is invalid'],
                ],
            ], 200),
        ]);

        $this->actingAs($context['actor'])
            ->postJson(route('orders.shipment.book', $context['order']), [
                'courier_id' => $context['courier']->id,
                'courier_city_id' => $context['city']->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('debug.failure_type', 'application_rejection')
            ->assertJsonPath('debug.http_status', 200)
            ->assertJsonPath('debug.response.success', false)
            ->assertJsonPath('debug.response.errors.city.0', 'The selected city is invalid');

        $this->assertDatabaseMissing('shipments', ['order_id' => $context['order']->id]);
    }

    public function test_json_assign_courier_royal_debug_never_exposes_credentials(): void
    {
        $context = $this->prepareRoyalAssignCourier(
            token: 'super-secret-bearer-token',
            password: 'super-secret-password',
        );

        Http::fake([
            '*/api/public/merchant/order/single' => Http::response([
                'message' => 'Rejected super-secret-password',
                'token' => 'super-secret-bearer-token',
                'password' => 'super-secret-password',
                'authorization' => 'Bearer super-secret-bearer-token',
                'errors' => [
                    'city' => ['City not found'],
                ],
            ], 422),
        ]);

        $response = $this->actingAs($context['actor'])
            ->postJson(route('orders.shipment.book', $context['order']), [
                'courier_id' => $context['courier']->id,
                'courier_city_id' => $context['city']->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('debug.http_status', 422)
            ->assertJsonPath('debug.response.errors.city.0', 'City not found')
            ->assertJsonMissingPath('debug.response.token')
            ->assertJsonMissingPath('debug.response.password')
            ->assertJsonMissingPath('debug.response.authorization');

        $encoded = json_encode($response->json());
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('super-secret-bearer-token', $encoded);
        $this->assertStringNotContainsString('super-secret-password', $encoded);
        $this->assertStringNotContainsString('Bearer ', $encoded);
        $this->assertStringNotContainsString('0771234123', $encoded);
        $this->assertStringNotContainsString('0779999888', $encoded);
    }

    public function test_json_assign_courier_does_not_duplicate_successful_booking(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.shipment.book']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier));
        $setup = $this->makeCourierSetup($order);

        $probe = new \stdClass;
        $probe->callCount = 0;
        $this->registerAdapter($setup['courier']->code, new class($probe) implements CourierBookingAdapter
        {
            public function __construct(private readonly object $probe)
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
                $this->probe->callCount++;

                return [
                    'tracking_number' => 'WB-ONCE',
                    'external_booking_ref' => null,
                    'raw_response' => ['ok' => true],
                ];
            }
        });

        $this->actingAs($actor)
            ->postJson(route('orders.shipment.book', $order), [
                'courier_id' => $setup['courier']->id,
                'courier_city_id' => $setup['city']->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.waybill', 'WB-ONCE');

        $callsAfterFirst = $probe->callCount;

        $this->actingAs($actor)
            ->postJson(route('orders.shipment.book', $order), [
                'courier_id' => $setup['courier']->id,
                'courier_city_id' => $setup['city']->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);

        $this->assertSame($callsAfterFirst, $probe->callCount);
        $this->assertSame(1, Shipment::query()->where('order_id', $order->id)->count());
    }

    public function test_call_center_bootstrap_includes_courier_name_and_waybill(): void
    {
        $actor = $this->makeResellerWithPermission([
            'orders.view',
            'orders.shipment.book',
        ]);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier));
        $setup = $this->makeCourierSetup($order);
        $this->registerSuccessfulAdapter($setup['courier']->code, 'WB-CC-77');

        $this->actingAs($actor)
            ->postJson(route('orders.shipment.book', $order), [
                'courier_id' => $setup['courier']->id,
                'courier_city_id' => $setup['city']->id,
            ])
            ->assertOk();

        $this->actingAs($actor)
            ->getJson(route('orders.index', ['workspace' => 'call-center', 'json' => 1]))
            ->assertOk()
            ->assertJsonFragment([
                'orderNumber' => $order->order_number,
                'courierName' => $setup['courier']->name,
                'trackingId' => 'WB-CC-77',
            ]);
    }

    /**
     * @param  list<string>  $permissionSlugs
     */
    private function makeResellerWithPermission(array $permissionSlugs, ?Company $company = null): User
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

        $company ??= Company::query()->create([
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
            'email' => 'user-'.Str::uuid().'@feeder.local',
            'phone' => '077'.random_int(100000, 999999),
            'password' => Hash::make('password'),
            'user_type' => UserType::OWNER->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
        ]);

        if ($company->owner_user_id === null) {
            $company->forceFill(['owner_user_id' => $user->id])->save();
            $this->configureResellerCompany($company, ['lk']);
        }

        $role = Role::query()->firstOrCreate(
            [
                'portal_id' => $portal->id,
                'slug' => 'owner-test-'.Str::lower(Str::random(4)),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'company_id' => null,
                'name' => 'Owner Test',
                'description' => 'Owner Test',
                'is_system' => false,
            ]
        );

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

    /**
     * @param  list<string>  $permissionSlugs
     */
    private function makeCcaWithPermission(Company $resellerCompany, array $permissionSlugs): User
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

        $role = Role::query()->firstOrCreate(
            [
                'portal_id' => $portal->id,
                'slug' => CallCenterAgentEligibilityService::ROLE_SLUG,
            ],
            [
                'uuid' => (string) Str::uuid(),
                'company_id' => null,
                'name' => 'Call Center Agent',
                'description' => 'CCA',
                'is_system' => true,
            ]
        );

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
        $role->permissions()->syncWithoutDetaching($permissionIds);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $resellerCompany->id,
            'role_id' => $role->id,
            'email' => 'cca-'.Str::uuid().'@feeder.local',
            'phone' => '076'.random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'user_type' => UserType::EMPLOYEE->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
        ]);

        UserProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'first_name' => 'Agent',
            'last_name' => Str::upper(Str::random(4)),
        ]);

        return $user->fresh(['profile', 'role.portal', 'company']);
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
    private function makeVariant(User $supplier, array $variantOverrides = []): ProductVariant
    {
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
            'market_id' => $this->marketByCode('lk')->id,
            'name' => 'Product '.Str::upper(Str::random(5)),
            'slug' => 'product-'.Str::lower(Str::random(8)),
            'status' => ProductStatus::ACTIVE->value,
            'system_visible' => true,
            'web_visible' => true,
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(User $reseller, User $supplier, ProductVariant $variant, array $overrides = []): Order
    {
        $market = $this->marketByCode('lk');
        $customer = Customer::query()->create([
            'uuid' => UuidService::generate(),
            'display_name' => 'Ship Customer',
            'primary_country_id' => $this->countryByIso('LK')->id,
            'is_banned' => false,
        ]);

        $order = Order::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'order_number' => 'ORD-'.Str::upper(Str::random(6)),
            'source' => OrderSource::MANUAL,
            'status' => OrderStatus::PENDING,
            'market_id' => $market->id,
            'currency_id' => $market->currency_id,
            'market_code_snapshot' => $market->code,
            'currency_code_snapshot' => 'LKR',
            'reseller_id' => $reseller->id,
            'reseller_company_id' => $reseller->company_id,
            'supplier_id' => $supplier->id,
            'customer_id' => $customer->id,
            'customer_name_snapshot' => 'Ship Customer',
            'primary_phone_snapshot' => '070'.random_int(1000000, 9999999),
            'primary_phone_country_id' => $this->countryByIso('LK')->id,
            'items_subtotal' => 250,
            'discount_amount' => 0,
            'courier_fee_amount' => 0,
            'customer_payable_amount' => 250,
            'total_weight' => 0.5,
            'after_hours' => false,
            'after_hours_warning_shown' => false,
            'after_hours_penalty_amount' => 0,
            'duplicate_warning_shown' => false,
            'duplicate_warning_overridden' => false,
            'created_by' => $reseller->id,
        ], $overrides));

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => 'Product Snapshot',
            'variant_name_snapshot' => $variant->name,
            'barcode_snapshot' => $variant->barcode,
            'quantity' => 1,
            'unit_selling_price' => 250,
            'unit_cost_snapshot' => 100,
            'unit_company_commission_snapshot' => 150,
            'unit_weight_snapshot' => 0.5,
            'line_selling_total' => 250,
            'line_weight_total' => 0.5,
        ]);

        OrderStatusHistory::query()->create([
            'order_id' => $order->id,
            'from_status' => null,
            'to_status' => OrderStatus::PENDING,
            'changed_by' => $reseller->id,
            'changed_by_company_id' => $reseller->company_id,
            'reason' => 'Order created',
        ]);

        return $order->fresh(['items']);
    }

    /**
     * @return array{courier: Courier, service: CourierService, city: CourierCity}
     */
    private function makeCourierSetup(Order $order, bool $withAccount = true): array
    {
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

        CourierCity::query()->create([
            'courier_id' => $courier->id,
            'district_name' => 'Colombo',
            'city_name' => 'Colombo 07',
            'external_city_code' => 'CMB07-'.Str::lower(Str::random(4)),
            'external_district_code' => 'COL',
            'is_active' => true,
        ]);

        CourierMarketPricing::query()->create([
            'courier_id' => $courier->id,
            'market_id' => $order->market_id,
            'currency_id' => $order->currency_id,
            'first_kg_fee' => 600.00,
            'additional_kg_fee' => 100.00,
            'is_active' => true,
        ]);

        if ($withAccount) {
            SupplierCourierAccount::query()->create([
                'supplier_id' => $order->supplier_id,
                'courier_id' => $courier->id,
                'account_label' => 'Primary',
                'credentials_encrypted' => Crypt::encryptString(json_encode([
                    'api_key' => 'secret-api-key',
                ], JSON_THROW_ON_ERROR)),
                'is_active' => true,
            ]);
        }

        return compact('courier', 'service', 'city');
    }

    private function registerSuccessfulAdapter(string $courierCode, string $trackingNumber): void
    {
        $this->registerAdapter($courierCode, new class($trackingNumber) implements CourierBookingAdapter
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
                    'raw_response' => [
                        'ok' => true,
                        'api_key' => 'should-not-persist',
                    ],
                ];
            }
        });
    }

    private function registerAdapter(string $courierCode, CourierBookingAdapter $adapter): void
    {
        app(CourierBookingAdapterResolver::class)->register($courierCode, $adapter);
    }

    /**
     * @return array{actor: User, order: Order, courier: Courier, city: CourierCity}
     */
    private function prepareRoyalAssignCourier(
        string $token = 'valid-booking-token',
        string $password = 'secret-password',
    ): array {
        config([
            'services.curfox.base_url' => 'https://v2-dashboards.api.curfox.com',
            'services.curfox.tenant' => 'royalexpress',
            'feeder.curfox.base_url' => 'https://v2-dashboards.api.curfox.com',
            'feeder.curfox.tenant' => 'royalexpress',
        ]);

        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.shipment.book']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier), [
            'customer_name_snapshot' => 'Ship Customer',
            'primary_phone_snapshot' => '0771234123',
            'secondary_phone_snapshot' => '0779999888',
            'items_subtotal' => 250,
            'discount_amount' => 0,
            'total_weight' => 0.5,
        ]);
        $setup = $this->makeCourierSetup($order);

        $account = SupplierCourierAccount::query()
            ->where('supplier_id', $order->supplier_id)
            ->where('courier_id', $setup['courier']->id)
            ->firstOrFail();
        $account->setCredentials([
            'email' => 'book@example.com',
            'password' => $password,
            'token' => $token,
        ]);
        $account->meta_json = [
            'merchant_business_id' => '2',
            'origin_city_name' => 'Aggona',
            'origin_state_name' => 'Colombo Suburbs',
        ];
        $account->save();

        OrderAddress::query()->create([
            'order_id' => $order->id,
            'recipient_name' => 'Ship Customer',
            'line1' => '12 Temple Road',
            'city_name' => 'Colombo 03',
            'district_name' => 'Colombo',
            'country_id' => $order->primary_phone_country_id,
            'full_address_text' => '12 Temple Road, Colombo 03',
        ]);

        $this->registerAdapter($setup['courier']->code, app(RoyalBookingAdapter::class));

        return [
            'actor' => $actor,
            'order' => $order->fresh(['items', 'address']),
            'courier' => $setup['courier'],
            'city' => $setup['city'],
        ];
    }
}
