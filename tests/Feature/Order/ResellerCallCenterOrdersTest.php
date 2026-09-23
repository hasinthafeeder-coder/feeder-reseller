<?php

namespace Tests\Feature\Order;

use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\OrderAssignmentState;
use Feeder\Core\Enums\OrderCcaAssignmentOrigin;
use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\OrderCcaAssignment;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\ProductCategory;
use Feeder\Core\Models\ProductVariant;
use Feeder\Core\Models\ResellerSupplierAssignment;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\User;
use Feeder\Core\Models\UserProfile;
use Feeder\Core\Services\Order\CallCenterAgentEligibilityService;
use Feeder\Core\Services\Order\OrderCcaAssignmentService;
use Feeder\Core\Services\UuidService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class ResellerCallCenterOrdersTest extends TestCase
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

    public function test_cca_sees_assigned_and_pool_orders_in_call_center_workspace(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.update']);
        $cca = $this->makeCca($owner->company, [
            'orders.view',
            'orders.update',
            'orders.status.update',
        ]);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $supplier);
        $variant = $this->makeVariant($supplier);

        $mine = $this->makeOrder($owner, $supplier, $variant, ['order_number' => 'CC-MINE-1']);
        $pool = $this->makeOrder($owner, $supplier, $variant, ['order_number' => 'CC-POOL-1']);
        $otherAssigned = $this->makeOrder($owner, $supplier, $variant, ['order_number' => 'CC-OTHER-1']);
        $otherCca = $this->makeCca($owner->company, ['orders.view', 'orders.update']);

        app(OrderCcaAssignmentService::class)->assign($mine, (int) $cca->id, (int) $owner->id);
        app(OrderCcaAssignmentService::class)->moveToPool($pool, (int) $owner->id);
        app(OrderCcaAssignmentService::class)->assign($otherAssigned, (int) $otherCca->id, (int) $owner->id);

        $this->actingAs($cca)
            ->getJson(route('orders.index', ['workspace' => 'call-center', 'tab' => 'assigned', 'cca_id' => $cca->id, 'json' => 1]))
            ->assertOk()
            ->assertJsonPath('data.workspace', 'call-center')
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonFragment(['orderNumber' => 'CC-MINE-1']);

        $this->actingAs($cca)
            ->getJson(route('orders.index', ['workspace' => 'call-center', 'tab' => 'pool', 'json' => 1]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonFragment(['orderNumber' => 'CC-POOL-1']);

        $this->actingAs($cca)
            ->get(route('orders.index', ['workspace' => 'call-center', 'tab' => 'assigned', 'cca_id' => $cca->id]))
            ->assertOk()
            ->assertSee('Call Center Orders')
            ->assertSee('CC-MINE-1')
            ->assertSee('"live":true', false)
            ->assertSee('"workspace":"call-center"', false);
    }

    public function test_cca_call_center_defaults_to_assigned_filtered_to_self(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.update']);
        $cca = $this->makeCca($owner->company, ['orders.view', 'orders.update']);
        $otherCca = $this->makeCca($owner->company, ['orders.view', 'orders.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $supplier);
        $variant = $this->makeVariant($supplier);

        $mine = $this->makeOrder($owner, $supplier, $variant, ['order_number' => 'CC-DEFAULT-MINE']);
        $theirs = $this->makeOrder($owner, $supplier, $variant, ['order_number' => 'CC-DEFAULT-THEIRS']);
        app(OrderCcaAssignmentService::class)->assign($mine, (int) $cca->id, (int) $owner->id);
        app(OrderCcaAssignmentService::class)->assign($theirs, (int) $otherCca->id, (int) $owner->id);

        $default = $this->actingAs($cca)
            ->getJson(route('orders.index', ['workspace' => 'call-center', 'json' => 1]))
            ->assertOk()
            ->assertJsonPath('data.filters.tab', 'assigned')
            ->assertJsonPath('data.filters.cca_id', (string) $cca->id)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonFragment(['orderNumber' => 'CC-DEFAULT-MINE'])
            ->json('data');

        $this->assertSame('cca', $default['actor']['role']);
        $this->assertFalse($default['actor']['can_assign']);

        $this->actingAs($cca)
            ->getJson(route('orders.index', [
                'workspace' => 'call-center',
                'tab' => 'assigned',
                'cca_id' => $otherCca->id,
                'json' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonFragment(['orderNumber' => 'CC-DEFAULT-THEIRS'])
            ->assertJsonPath('data.orders.0.isEditable', false);
    }

    public function test_cca_cannot_see_other_company_orders(): void
    {
        $ownerA = $this->makeResellerWithPermission(['orders.view']);
        $ownerB = $this->makeResellerWithPermission(['orders.view']);
        $ccaA = $this->makeCca($ownerA->company, ['orders.view', 'orders.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($ownerA, $supplier);
        $this->assignSupplier($ownerB, $supplier);
        $variant = $this->makeVariant($supplier);

        $foreign = $this->makeOrder($ownerB, $supplier, $variant, ['order_number' => 'CC-FOREIGN-1']);
        app(OrderCcaAssignmentService::class)->moveToPool($foreign, (int) $ownerB->id);

        $this->actingAs($ccaA)
            ->getJson(route('orders.index', ['workspace' => 'call-center', 'tab' => 'pool', 'json' => 1]))
            ->assertOk()
            ->assertJsonMissing(['orderNumber' => 'CC-FOREIGN-1']);

        $this->actingAs($ccaA)
            ->get(route('orders.show', $foreign))
            ->assertNotFound();

        $this->actingAs($ccaA)
            ->postJson(route('orders.cca.claim', $foreign))
            ->assertNotFound();
    }

    public function test_owner_call_center_assignment_remains_functional_without_becoming_cca(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.update', 'orders.status.update']);
        $cca = $this->makeCca($owner->company, ['orders.view', 'orders.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $supplier);
        $variant = $this->makeVariant($supplier);
        $order = $this->makeOrder($owner, $supplier, $variant, ['order_number' => 'CC-OWNER-1']);

        $this->actingAs($owner)
            ->postJson(route('orders.bulk.pool'), ['order_ids' => [$order->id]])
            ->assertOk();

        $this->assertSame(OrderAssignmentState::POOL, $order->fresh()->assignmentState());
        $this->assertNull($order->fresh()->cca_id);

        $this->actingAs($owner)
            ->postJson(route('orders.bulk.assign'), [
                'order_ids' => [$order->id],
                'cca_id' => $cca->id,
            ])
            ->assertOk();

        $fresh = $order->fresh();
        $this->assertSame((int) $cca->id, (int) $fresh->cca_id);
        $this->assertNotSame((int) $owner->id, (int) $fresh->cca_id);

        $this->actingAs($owner)
            ->post(route('orders.status.update', $order), [
                'status' => OrderStatus::HOLD->value,
            ])
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame((int) $cca->id, (int) $order->fresh()->cca_id);
        $this->assertSame(OrderStatus::HOLD, $order->fresh()->status);
    }

    public function test_eligible_cca_can_claim_pool_order_atomically(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.update']);
        $cca = $this->makeCca($owner->company, ['orders.view', 'orders.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $supplier);
        $order = $this->makeOrder($owner, $supplier, $this->makeVariant($supplier), [
            'order_number' => 'CC-CLAIM-1',
            'status' => OrderStatus::PENDING->value,
        ]);

        app(OrderCcaAssignmentService::class)->moveToPool($order, (int) $owner->id);

        $this->actingAs($cca)
            ->postJson(route('orders.cca.claim', $order))
            ->assertOk()
            ->assertJsonPath('data.ccaId', (int) $cca->id)
            ->assertJsonPath('data.assignment', OrderAssignmentState::ASSIGNED->value);

        $fresh = $order->fresh(['ccaAssignments']);
        $this->assertSame((int) $cca->id, (int) $fresh->cca_id);
        $this->assertFalse((bool) $fresh->available_in_pool);
        $this->assertSame(OrderStatus::PENDING, $fresh->status);
        $this->assertSame(
            OrderCcaAssignmentOrigin::POOL_CLAIM,
            OrderCcaAssignment::query()->where('order_id', $fresh->id)->whereNull('unassigned_at')->first()->origin
        );
    }

    public function test_cca_cannot_claim_while_active_pool_claim_blocks(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.update']);
        $cca = $this->makeCca($owner->company, ['orders.view', 'orders.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $supplier);
        $variant = $this->makeVariant($supplier);

        $first = $this->makeOrder($owner, $supplier, $variant, ['order_number' => 'CC-BLOCK-1']);
        $second = $this->makeOrder($owner, $supplier, $variant, ['order_number' => 'CC-BLOCK-2']);
        $service = app(OrderCcaAssignmentService::class);
        $service->moveToPool($first, (int) $owner->id);
        $service->moveToPool($second, (int) $owner->id);

        $this->actingAs($cca)
            ->postJson(route('orders.cca.claim', $first))
            ->assertOk();

        $this->actingAs($cca)
            ->postJson(route('orders.cca.claim', $second))
            ->assertStatus(422);

        $this->assertNull($second->fresh()->cca_id);
        $this->assertTrue((bool) $second->fresh()->available_in_pool);
    }

    public function test_second_cca_cannot_claim_already_claimed_pool_order(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.update']);
        $ccaOne = $this->makeCca($owner->company, ['orders.view', 'orders.update']);
        $ccaTwo = $this->makeCca($owner->company, ['orders.view', 'orders.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $supplier);
        $order = $this->makeOrder($owner, $supplier, $this->makeVariant($supplier));

        app(OrderCcaAssignmentService::class)->moveToPool($order, (int) $owner->id);

        $this->actingAs($ccaOne)
            ->postJson(route('orders.cca.claim', $order))
            ->assertOk();

        $this->actingAs($ccaTwo)
            ->postJson(route('orders.cca.claim', $order))
            ->assertStatus(422);

        $this->assertSame((int) $ccaOne->id, (int) $order->fresh()->cca_id);
    }

    public function test_cca_restrictions_reject_discount_assign_pool_and_unassign(): void
    {
        $owner = $this->makeResellerWithPermission([
            'orders.view',
            'orders.cca.assign',
            'orders.update',
            'orders.discount.update',
            'orders.status.update',
        ]);
        $cca = $this->makeCca($owner->company, [
            'orders.view',
            'orders.update',
            'orders.status.update',
            'orders.discount.update',
            'orders.cca.assign',
        ]);
        $otherCca = $this->makeCca($owner->company, ['orders.view', 'orders.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $supplier);
        $variant = $this->makeVariant($supplier);
        $order = $this->makeOrder($owner, $supplier, $variant, [
            'items_subtotal' => 250,
            'discount_amount' => 0,
            'customer_payable_amount' => 250,
        ]);

        app(OrderCcaAssignmentService::class)->assign($order, (int) $cca->id, (int) $owner->id);

        $this->actingAs($cca)
            ->post(route('orders.discount.update', $order), ['discount_amount' => 10])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('order');

        $this->actingAs($cca)
            ->post(route('orders.cca.assign', $order), ['cca_id' => $otherCca->id])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('order');

        $this->actingAs($cca)
            ->postJson(route('orders.bulk.pool'), ['order_ids' => [$order->id]])
            ->assertStatus(422);

        $this->actingAs($cca)
            ->postJson(route('orders.bulk.unassign'), ['order_ids' => [$order->id]])
            ->assertStatus(422);

        $fresh = $order->fresh();
        $this->assertSame((int) $cca->id, (int) $fresh->cca_id);
        $this->assertSame('0.00', $fresh->discount_amount);
        $this->assertFalse((bool) $fresh->available_in_pool);
    }

    public function test_cca_can_transition_own_order_but_not_another_agents_order(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.update']);
        $cca = $this->makeCca($owner->company, ['orders.view', 'orders.update', 'orders.status.update']);
        $otherCca = $this->makeCca($owner->company, ['orders.view', 'orders.update', 'orders.status.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $supplier);
        $variant = $this->makeVariant($supplier);

        $mine = $this->makeOrder($owner, $supplier, $variant, ['order_number' => 'CC-STATUS-MINE']);
        $theirs = $this->makeOrder($owner, $supplier, $variant, ['order_number' => 'CC-STATUS-THEIRS']);
        app(OrderCcaAssignmentService::class)->assign($mine, (int) $cca->id, (int) $owner->id);
        app(OrderCcaAssignmentService::class)->assign($theirs, (int) $otherCca->id, (int) $owner->id);

        $this->actingAs($cca)
            ->post(route('orders.status.update', $mine), [
                'status' => OrderStatus::FIRST_ATTEMPT->value,
            ])
            ->assertRedirect(route('orders.show', $mine));

        $this->assertSame(OrderStatus::FIRST_ATTEMPT, $mine->fresh()->status);

        $this->actingAs($cca)
            ->post(route('orders.status.update', $theirs), [
                'status' => OrderStatus::HOLD->value,
            ])
            ->assertRedirect(route('orders.show', $theirs))
            ->assertSessionHasErrors('order');

        $this->assertSame(OrderStatus::PENDING, $theirs->fresh()->status);
    }

    public function test_call_center_detail_shows_real_order_data_for_cca(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.update']);
        $cca = $this->makeCca($owner->company, ['orders.view', 'orders.update', 'orders.status.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $supplier);
        $variant = $this->makeVariant($supplier);
        $order = $this->makeOrder($owner, $supplier, $variant, [
            'order_number' => 'CC-DETAIL-1',
            'customer_name_snapshot' => 'Call Center Customer',
            'primary_phone_snapshot' => '0701122334',
        ]);
        $this->attachOrderLineAndAddress($order, $variant);
        app(OrderCcaAssignmentService::class)->assign($order, (int) $cca->id, (int) $owner->id);

        $this->actingAs($cca)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('CC-DETAIL-1')
            ->assertSee('Call Center Customer')
            ->assertSee('0701122334')
            ->assertSee('id="manualOrderForm"', false)
            ->assertSee('Save changes')
            ->assertSee('name="customer_name"', false)
            ->assertDontSee('Update discount');
    }

    public function test_cca_can_update_assigned_order_via_create_form_fields(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.update']);
        $cca = $this->makeCca($owner->company, ['orders.view', 'orders.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $supplier);
        $variant = $this->makeVariant($supplier);
        $order = $this->makeOrder($owner, $supplier, $variant, [
            'order_number' => 'CC-EDIT-1',
            'customer_name_snapshot' => 'Before Name',
            'primary_phone_snapshot' => '0701000001',
            'discount_amount' => 10,
            'customer_payable_amount' => 240,
        ]);
        $this->attachOrderLineAndAddress($order, $variant);
        app(OrderCcaAssignmentService::class)->assign($order, (int) $cca->id, (int) $owner->id);

        $response = $this->actingAs($cca)
            ->from(route('orders.show', $order))
            ->post(route('orders.update', $order), [
                'market_id' => $order->market_id,
                'supplier_id' => $order->supplier_id,
                'customer_name' => 'After Name',
                'primary_phone' => '0701999888',
                'address_line1' => '99 Updated Street',
                'city_name' => 'Colombo',
                'district_name' => 'Colombo',
                'items' => [
                    [
                        'product_variant_id' => $variant->id,
                        'quantity' => 2,
                        'selected_selling_price' => 250,
                    ],
                ],
                'discount_amount' => 99,
            ]);

        $response->assertSessionDoesntHaveErrors();
        $response->assertRedirect(route('orders.show', $order));
        $response->assertSessionHas('success');

        $fresh = $order->fresh(['items', 'address']);
        $this->assertSame('After Name', $fresh->customer_name_snapshot);
        $this->assertSame('0701999888', $fresh->primary_phone_snapshot);
        $this->assertSame('99 Updated Street', $fresh->address->line1);
        $this->assertSame(2, (int) $fresh->items->first()->quantity);
        // CCA cannot change discount.
        $this->assertSame('10.00', $fresh->discount_amount);
    }

    public function test_cca_cannot_update_another_agents_order(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.update']);
        $cca = $this->makeCca($owner->company, ['orders.view', 'orders.update']);
        $other = $this->makeCca($owner->company, ['orders.view', 'orders.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $supplier);
        $variant = $this->makeVariant($supplier);
        $order = $this->makeOrder($owner, $supplier, $variant, ['order_number' => 'CC-EDIT-OTHER']);
        $this->attachOrderLineAndAddress($order, $variant);
        app(OrderCcaAssignmentService::class)->assign($order, (int) $other->id, (int) $owner->id);

        $this->actingAs($cca)
            ->from(route('orders.show', $order))
            ->post(route('orders.update', $order), [
                'market_id' => $order->market_id,
                'supplier_id' => $order->supplier_id,
                'customer_name' => 'Hacked',
                'primary_phone' => '0701555666',
                'address_line1' => '1 Bad Lane',
                'items' => [
                    [
                        'product_variant_id' => $variant->id,
                        'quantity' => 1,
                        'selected_selling_price' => 250,
                    ],
                ],
            ])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('order');

        $this->assertSame('Customer', $order->fresh()->customer_name_snapshot);
    }

    public function test_owner_can_update_company_order_and_cancelled_orders_are_rejected(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.view', 'orders.update', 'orders.cca.assign']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $supplier);
        $variant = $this->makeVariant($supplier);

        $order = $this->makeOrder($owner, $supplier, $variant, [
            'order_number' => 'CC-EDIT-OWNER',
            'customer_name_snapshot' => 'Owner Customer',
        ]);
        $this->attachOrderLineAndAddress($order, $variant);

        $this->actingAs($owner)
            ->post(route('orders.update', $order), [
                'market_id' => $order->market_id,
                'supplier_id' => $order->supplier_id,
                'customer_name' => 'Owner Updated',
                'primary_phone' => '0701222333',
                'address_line1' => '7 Owner Avenue',
                'items' => [
                    [
                        'product_variant_id' => $variant->id,
                        'quantity' => 1,
                        'selected_selling_price' => 250,
                    ],
                ],
                'discount_amount' => 5,
            ])
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame('Owner Updated', $order->fresh()->customer_name_snapshot);
        $this->assertSame('5.00', $order->fresh()->discount_amount);

        $cancelled = $this->makeOrder($owner, $supplier, $variant, [
            'order_number' => 'CC-EDIT-CANCELLED',
            'status' => OrderStatus::CANCELLED->value,
            'cancelled_at' => now(),
        ]);
        $this->attachOrderLineAndAddress($cancelled, $variant);

        $this->actingAs($owner)
            ->from(route('orders.show', $cancelled))
            ->post(route('orders.update', $cancelled), [
                'market_id' => $cancelled->market_id,
                'supplier_id' => $cancelled->supplier_id,
                'customer_name' => 'Should Fail',
                'primary_phone' => '0701444555',
                'address_line1' => 'Nope',
                'items' => [
                    [
                        'product_variant_id' => $variant->id,
                        'quantity' => 1,
                        'selected_selling_price' => 250,
                    ],
                ],
            ])
            ->assertRedirect(route('orders.show', $cancelled))
            ->assertSessionHasErrors('order');
    }

    public function test_call_center_status_filter_query_keeps_workspace_and_filters_rows(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $supplier);
        $variant = $this->makeVariant($supplier);

        $pending = $this->makeOrder($owner, $supplier, $variant, [
            'order_number' => 'CC-FILTER-PENDING',
            'status' => OrderStatus::PENDING->value,
        ]);
        $hold = $this->makeOrder($owner, $supplier, $variant, [
            'order_number' => 'CC-FILTER-HOLD',
            'status' => OrderStatus::HOLD->value,
        ]);

        // Mimic the URL the status chips now build: single "?" with workspace + filters.
        $this->actingAs($owner)
            ->get(route('orders.index', [
                'workspace' => 'call-center',
                'tab' => 'all',
                'status' => OrderStatus::HOLD->value,
                'page' => 1,
            ]))
            ->assertOk()
            ->assertSee('Call Center Orders')
            ->assertSee('CC-FILTER-HOLD')
            ->assertDontSee('CC-FILTER-PENDING')
            ->assertSee('"status":"HOLD"', false)
            ->assertSee('"workspace":"call-center"', false);

        $this->actingAs($owner)
            ->getJson(route('orders.index', [
                'workspace' => 'call-center',
                'tab' => 'all',
                'status' => OrderStatus::PENDING->value,
                'json' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('data.workspace', 'call-center')
            ->assertJsonPath('data.filters.status', OrderStatus::PENDING->value)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonFragment(['orderNumber' => 'CC-FILTER-PENDING'])
            ->assertJsonMissing(['orderNumber' => 'CC-FILTER-HOLD']);

        $this->assertNotNull($pending->fresh());
        $this->assertNotNull($hold->fresh());
    }

    public function test_call_center_product_filter_returns_orders_containing_selected_product(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $supplier);

        $variantA = $this->makeVariant($supplier);
        $variantB = $this->makeVariant($supplier);

        $orderWithA = $this->makeOrder($owner, $supplier, $variantA, [
            'order_number' => 'CC-PROD-A',
        ]);
        $this->attachOrderLineAndAddress($orderWithA, $variantA);

        $orderWithB = $this->makeOrder($owner, $supplier, $variantB, [
            'order_number' => 'CC-PROD-B',
        ]);
        $this->attachOrderLineAndAddress($orderWithB, $variantB);

        $orderWithBoth = $this->makeOrder($owner, $supplier, $variantA, [
            'order_number' => 'CC-PROD-BOTH',
        ]);
        $this->attachOrderLineAndAddress($orderWithBoth, $variantA);
        $variantB->loadMissing('product');
        \Feeder\Core\Models\OrderItem::query()->create([
            'order_id' => $orderWithBoth->id,
            'product_id' => $variantB->product_id,
            'product_variant_id' => $variantB->id,
            'product_name_snapshot' => $variantB->product->name,
            'variant_name_snapshot' => $variantB->name,
            'barcode_snapshot' => $variantB->barcode,
            'quantity' => 1,
            'unit_selling_price' => 250,
            'unit_cost_snapshot' => 100,
            'unit_company_commission_snapshot' => 150,
            'unit_weight_snapshot' => 0.5,
            'line_selling_total' => 250,
            'line_weight_total' => 0.5,
        ]);

        $this->actingAs($owner)
            ->getJson(route('orders.index', [
                'workspace' => 'call-center',
                'tab' => 'all',
                'product_id' => $variantA->product_id,
                'json' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('data.filters.product_id', (string) $variantA->product_id)
            ->assertJsonPath('data.selected_product.id', $variantA->product_id)
            ->assertJsonFragment(['orderNumber' => 'CC-PROD-A'])
            ->assertJsonFragment(['orderNumber' => 'CC-PROD-BOTH'])
            ->assertJsonMissing(['orderNumber' => 'CC-PROD-B']);
    }

    public function test_call_center_date_from_to_filters_remain_independent_of_product_filter(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $supplier);
        $variant = $this->makeVariant($supplier);

        $inside = $this->makeOrder($owner, $supplier, $variant, [
            'order_number' => 'CC-DATE-IN',
        ]);
        $this->attachOrderLineAndAddress($inside, $variant);
        $inside->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();

        $outside = $this->makeOrder($owner, $supplier, $variant, [
            'order_number' => 'CC-DATE-OUT',
        ]);
        $this->attachOrderLineAndAddress($outside, $variant);
        $outside->forceFill(['created_at' => now()->subDays(10)])->saveQuietly();

        $from = now()->subDays(5)->toDateString();
        $to = now()->toDateString();

        $this->actingAs($owner)
            ->getJson(route('orders.index', [
                'workspace' => 'call-center',
                'tab' => 'all',
                'date_from' => $from,
                'date_to' => $to,
                'json' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('data.filters.date_from', $from)
            ->assertJsonPath('data.filters.date_to', $to)
            ->assertJsonFragment(['orderNumber' => 'CC-DATE-IN'])
            ->assertJsonMissing(['orderNumber' => 'CC-DATE-OUT']);

        $this->actingAs($owner)
            ->getJson(route('orders.index', [
                'workspace' => 'call-center',
                'tab' => 'all',
                'product_id' => $variant->product_id,
                'date_from' => $from,
                'date_to' => $to,
                'json' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('data.filters.product_id', (string) $variant->product_id)
            ->assertJsonPath('data.filters.date_from', $from)
            ->assertJsonPath('data.filters.date_to', $to)
            ->assertJsonFragment(['orderNumber' => 'CC-DATE-IN'])
            ->assertJsonMissing(['orderNumber' => 'CC-DATE-OUT']);
    }

    public function test_call_center_unauthorized_product_filter_yields_empty_results(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.update']);
        $assignedSupplier = $this->makeSupplierUser();
        $foreignSupplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $assignedSupplier);

        $assignedVariant = $this->makeVariant($assignedSupplier);
        $foreignVariant = $this->makeVariant($foreignSupplier);

        $order = $this->makeOrder($owner, $assignedSupplier, $assignedVariant, [
            'order_number' => 'CC-PROD-AUTH',
        ]);
        $this->attachOrderLineAndAddress($order, $assignedVariant);

        $this->actingAs($owner)
            ->getJson(route('orders.index', [
                'workspace' => 'call-center',
                'tab' => 'all',
                'product_id' => $foreignVariant->product_id,
                'json' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 0)
            ->assertJsonPath('data.selected_product', null)
            ->assertJsonMissing(['orderNumber' => 'CC-PROD-AUTH']);
    }

    public function test_call_center_filter_products_endpoint_only_returns_accessible_products(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.view']);
        $assignedSupplier = $this->makeSupplierUser();
        $foreignSupplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $assignedSupplier);

        $assignedVariant = $this->makeVariant($assignedSupplier);
        $foreignVariant = $this->makeVariant($foreignSupplier);

        $this->actingAs($owner)
            ->getJson(route('orders.filter-products', [
                'search' => $assignedVariant->product->name,
            ]))
            ->assertOk()
            ->assertJsonFragment(['id' => $assignedVariant->product_id])
            ->assertJsonMissing(['id' => $foreignVariant->product_id]);

        $this->actingAs($owner)
            ->getJson(route('orders.filter-products', [
                'search' => $foreignVariant->product->name,
            ]))
            ->assertOk()
            ->assertJsonMissing(['id' => $foreignVariant->product_id]);
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

        if ($company === null) {
            $company = Company::query()->create([
                'uuid' => (string) Str::uuid(),
                'portal_id' => $portal->id,
                'name' => 'Reseller Co '.Str::lower(Str::random(4)),
                'email' => 'reseller-'.Str::uuid().'@feeder.local',
                'phone' => '077'.random_int(100000, 999999),
                'registration_number' => 'REG-'.Str::random(6),
                'status' => CompanyStatus::ACTIVE->value,
            ]);
        }

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'email' => 'owner-'.Str::uuid().'@feeder.local',
            'phone' => '077'.random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'user_type' => UserType::OWNER->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
        ]);

        $company->forceFill(['owner_user_id' => $user->id])->save();
        $this->configureResellerCompany($company, ['lk']);
        $this->attachPermissions($user, $portal, $permissionSlugs);

        return $user->fresh(['company']);
    }

    /**
     * @param  list<string>  $permissionSlugs
     */
    private function makeCca(Company $company, array $permissionSlugs): User
    {
        $portal = Portal::query()->where('code', PortalCode::RESELLER->value)->firstOrFail();
        $role = Role::query()->firstOrCreate(
            [
                'portal_id' => $portal->id,
                'slug' => CallCenterAgentEligibilityService::ROLE_SLUG,
            ],
            [
                'uuid' => UuidService::generate(),
                'company_id' => null,
                'name' => 'Call Center Agent',
                'description' => 'CCA',
                'is_system' => true,
            ]
        );

        $cca = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
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
            'user_id' => $cca->id,
            'first_name' => 'Test',
            'last_name' => 'Agent',
        ]);

        $this->attachPermissions($cca, $portal, $permissionSlugs);

        return $cca->fresh(['role.portal', 'profile']);
    }

    /**
     * @param  list<string>  $permissionSlugs
     */
    private function attachPermissions(User $user, Portal $portal, array $permissionSlugs): void
    {
        $role = Role::query()->firstOrCreate(
            [
                'portal_id' => $portal->id,
                'slug' => 'test-role-'.Str::lower(Str::random(6)),
            ],
            [
                'uuid' => (string) Str::uuid(),
                'company_id' => $user->company_id,
                'name' => 'Test Role',
                'description' => 'Test',
                'is_system' => false,
            ]
        );

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
                    'sort_order' => 1,
                ]
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
            $user->directPermissions()->syncWithoutDetaching([
                $permission->id => ['allowed' => true],
            ]);
        }

        if ($user->role_id === null) {
            $user->forceFill(['role_id' => $role->id])->save();
        }
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
            'name' => 'Supplier Co '.Str::lower(Str::random(4)),
            'email' => 'supplier-'.Str::uuid().'@feeder.local',
            'phone' => '075'.random_int(100000, 999999),
            'status' => CompanyStatus::ACTIVE->value,
            'operation_market_id' => $this->marketByCode('lk')->id,
        ]);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'email' => 'supplier-user-'.Str::uuid().'@feeder.local',
            'phone' => '075'.random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'user_type' => UserType::OWNER->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
        ]);

        $company->forceFill(['owner_user_id' => $user->id])->save();

        return $user;
    }

    private function assignSupplier(User $reseller, User $supplier): void
    {
        ResellerSupplierAssignment::query()->firstOrCreate([
            'reseller_id' => $reseller->id,
            'supplier_id' => $supplier->id,
        ], [
            'uuid' => (string) Str::uuid(),
            'assigned_by' => $reseller->id,
        ]);
    }

    private function makeVariant(User $supplier): ProductVariant
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
            'price_locked' => true,
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ]);

        return ProductVariant::query()->create([
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
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(User $reseller, User $supplier, ProductVariant $variant, array $overrides = []): Order
    {
        $countryId = $this->countryByIso('LK')->id;

        return Order::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'order_number' => 'ORD-'.Str::upper(Str::random(8)),
            'source' => 'MANUAL',
            'status' => OrderStatus::PENDING->value,
            'market_id' => $this->marketByCode('lk')->id,
            'currency_id' => $this->marketByCode('lk')->currency_id,
            'market_code_snapshot' => 'LK',
            'currency_code_snapshot' => 'LKR',
            'reseller_id' => $reseller->id,
            'reseller_company_id' => $reseller->company_id,
            'supplier_id' => $supplier->id,
            'customer_id' => $this->makeCustomer($countryId)->id,
            'available_in_pool' => false,
            'customer_name_snapshot' => 'Customer',
            'primary_phone_snapshot' => '070'.random_int(1000000, 9999999),
            'primary_phone_country_id' => $countryId,
            'items_subtotal' => 250,
            'customer_payable_amount' => 250,
            'created_by' => $reseller->id,
        ], $overrides));
    }

    private function attachOrderLineAndAddress(Order $order, ProductVariant $variant): void
    {
        $variant->loadMissing('product');
        $countryId = (int) $order->primary_phone_country_id;

        \Feeder\Core\Models\OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => $variant->product->name,
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

        \Feeder\Core\Models\OrderAddress::query()->create([
            'order_id' => $order->id,
            'recipient_name' => $order->customer_name_snapshot,
            'line1' => '12 Test Street',
            'city_name' => 'Colombo',
            'district_name' => 'Colombo',
            'country_id' => $countryId,
            'full_address_text' => '12 Test Street',
        ]);
    }

    private function makeCustomer(int $countryId): \Feeder\Core\Models\Customer
    {
        return \Feeder\Core\Models\Customer::query()->create([
            'uuid' => (string) Str::uuid(),
            'display_name' => 'Customer '.Str::random(4),
            'primary_country_id' => $countryId,
            'is_banned' => false,
        ]);
    }
}
