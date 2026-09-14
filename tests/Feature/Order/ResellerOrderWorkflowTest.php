<?php

namespace Tests\Feature\Order;

use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\OrderCommentContextType;
use Feeder\Core\Enums\OrderSource;
use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Customer;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\OrderCcaAssignment;
use Feeder\Core\Models\OrderComment;
use Feeder\Core\Models\OrderItem;
use Feeder\Core\Models\OrderStatusHistory;
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
use Feeder\Core\Services\Order\OrderStatusService;
use Feeder\Core\Services\UuidService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class ResellerOrderWorkflowTest extends TestCase
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

    public function test_unauthorized_user_cannot_view_order(): void
    {
        $reseller = $this->makeResellerWithPermission(['products.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $order = $this->makeOrder($reseller, $supplier, $this->makeVariant($supplier));

        $this->actingAs($reseller)
            ->get(route('orders.show', $order))
            ->assertForbidden();
    }

    public function test_cross_company_order_returns_not_found(): void
    {
        $resellerA = $this->makeResellerWithPermission(['orders.view']);
        $resellerB = $this->makeResellerWithPermission(['orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($resellerA, $supplier);
        $this->assignSupplier($resellerB, $supplier);
        $orderB = $this->makeOrder($resellerB, $supplier, $this->makeVariant($supplier));

        $this->actingAs($resellerA)
            ->get(route('orders.show', $orderB))
            ->assertNotFound();
    }

    public function test_status_permission_required_and_arbitrary_transitions_work(): void
    {
        $viewer = $this->makeResellerWithPermission(['orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($viewer, $supplier);
        $variant = $this->makeVariant($supplier);
        $order = $this->makeOrder($viewer, $supplier, $variant, [
            'items_subtotal' => 250,
            'customer_payable_amount' => 250,
        ]);

        $this->actingAs($viewer)
            ->post(route('orders.status.update', $order), ['status' => OrderStatus::HOLD->value])
            ->assertForbidden();

        $actor = $this->makeResellerWithPermission([
            'orders.view',
            'orders.status.update',
        ], $viewer->company);

        $this->actingAs($actor)
            ->post(route('orders.status.update', $order), [
                'status' => OrderStatus::HOLD->value,
                'reason' => 'Call back later',
            ])
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame(OrderStatus::HOLD, $order->fresh()->status);

        $this->actingAs($actor)
            ->post(route('orders.status.update', $order), [
                'status' => OrderStatus::CONFIRMED->value,
            ])
            ->assertRedirect(route('orders.show', $order));

        $confirmed = $order->fresh();
        $this->assertSame(OrderStatus::CONFIRMED, $confirmed->status);
        $this->assertNotNull($confirmed->confirmed_at);
        $this->assertNotNull($confirmed->discount_locked_at);

        $this->actingAs($actor)
            ->post(route('orders.status.update', $order), [
                'status' => OrderStatus::FIRST_ATTEMPT->value,
            ])
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame(OrderStatus::FIRST_ATTEMPT, $order->fresh()->status);
        $this->assertGreaterThanOrEqual(4, OrderStatusHistory::query()->where('order_id', $order->id)->count());
    }

    public function test_cancel_and_reactivate_within_window(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.status.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier));

        $this->actingAs($actor)
            ->post(route('orders.status.update', $order), [
                'status' => OrderStatus::CANCELLED->value,
                'reason' => 'Customer declined',
            ])
            ->assertRedirect(route('orders.show', $order));

        $cancelled = $order->fresh();
        $this->assertSame(OrderStatus::CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);

        $this->actingAs($actor)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number);

        $this->actingAs($actor)
            ->post(route('orders.status.update', $order), [
                'status' => OrderStatus::PENDING->value,
                'reason' => 'Customer changed mind',
            ])
            ->assertRedirect(route('orders.show', $order));

        $reactivated = $order->fresh();
        $this->assertSame(OrderStatus::PENDING, $reactivated->status);
        $this->assertNotNull($reactivated->reactivated_at);
        $this->assertNull($reactivated->operations_hidden_at);
    }

    public function test_reactivation_outside_window_rejected(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.status.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier));

        app(OrderStatusService::class)->transition(
            $order,
            OrderStatus::CANCELLED,
            $actor->id,
            $actor->company_id,
            null,
            $actor->company_id,
        );

        $order->fresh()->forceFill([
            'cancelled_at' => now()->subDays(OrderStatusService::OPERATIONAL_VISIBILITY_DAYS + 2),
            'operations_hidden_at' => now()->subDay(),
        ])->save();

        $this->actingAs($actor)
            ->post(route('orders.status.update', $order), [
                'status' => OrderStatus::PENDING->value,
            ])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::CANCELLED, $order->fresh()->status);
    }

    public function test_cca_assign_permission_and_eligibility(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier));
        $cca = $this->makeCca($actor->company);

        $this->actingAs($actor)
            ->post(route('orders.cca.assign', $order), ['cca_id' => $cca->id])
            ->assertForbidden();

        $assigner = $this->makeResellerWithPermission([
            'orders.view',
            'orders.cca.assign',
        ], $actor->company);

        $this->actingAs($assigner)
            ->post(route('orders.cca.assign', $order), [
                'cca_id' => $cca->id,
                'note' => 'First assignment',
            ])
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame($cca->id, $order->fresh()->cca_id);
        $this->assertSame(1, OrderCcaAssignment::query()->where('order_id', $order->id)->count());

        $ccaTwo = $this->makeCca($actor->company);
        $this->actingAs($assigner)
            ->post(route('orders.cca.assign', $order), ['cca_id' => $ccaTwo->id])
            ->assertRedirect(route('orders.show', $order));

        $order = $order->fresh(['ccaAssignments']);
        $this->assertSame($ccaTwo->id, $order->cca_id);
        $this->assertCount(2, $order->ccaAssignments);
        $this->assertNotNull($order->ccaAssignments->first()->unassigned_at);

        $otherCompany = $this->makeResellerWithPermission(['orders.view'])->company;
        $wrongCompanyCca = $this->makeCca($otherCompany);

        $this->actingAs($assigner)
            ->post(route('orders.cca.assign', $order), ['cca_id' => $wrongCompanyCca->id])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('cca_id');

        $nonCca = $this->makeResellerWithPermission(['orders.view'], $actor->company);
        $this->actingAs($assigner)
            ->post(route('orders.cca.assign', $order), ['cca_id' => $nonCca->id])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('cca_id');

        $inactive = $this->makeCca($actor->company);
        $inactive->forceFill(['status' => UserStatus::SUSPENDED->value])->save();

        $this->actingAs($assigner)
            ->post(route('orders.cca.assign', $order), ['cca_id' => $inactive->id])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('cca_id');
    }

    public function test_comments_permission_append_and_author(): void
    {
        $viewer = $this->makeResellerWithPermission(['orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($viewer, $supplier);
        $order = $this->makeOrder($viewer, $supplier, $this->makeVariant($supplier));

        $this->actingAs($viewer)
            ->post(route('orders.comments.store', $order), [
                'body' => 'Should fail',
                'context_type' => OrderCommentContextType::ORDER->value,
            ])
            ->assertForbidden();

        $actor = $this->makeResellerWithPermission([
            'orders.view',
            'orders.comments.create',
        ], $viewer->company);

        $this->actingAs($actor)
            ->post(route('orders.comments.store', $order), [
                'body' => 'Customer confirmed address',
                'context_type' => OrderCommentContextType::CUSTOMER->value,
            ])
            ->assertRedirect(route('orders.show', $order));

        $this->actingAs($actor)
            ->post(route('orders.comments.store', $order), [
                'body' => 'Internal ops note',
                'context_type' => OrderCommentContextType::ORDER->value,
            ])
            ->assertRedirect(route('orders.show', $order));

        $comments = OrderComment::query()->where('order_id', $order->id)->orderBy('id')->get();
        $this->assertCount(2, $comments);
        $this->assertSame((int) $actor->id, (int) $comments[0]->author_user_id);
        $this->assertSame(OrderCommentContextType::CUSTOMER, $comments[0]->context_type);
        $this->assertSame('Customer confirmed address', $comments[0]->body);
        $this->assertSame('Internal ops note', $comments[1]->body);
    }

    public function test_discount_permission_lock_and_persist_snapshots(): void
    {
        $viewer = $this->makeResellerWithPermission(['orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($viewer, $supplier);
        $variant = $this->makeVariant($supplier, ['selling_price' => 250.00]);
        $order = $this->makeOrder($viewer, $supplier, $variant, [
            'items_subtotal' => 250,
            'discount_amount' => 0,
            'customer_payable_amount' => 250,
        ]);

        $this->actingAs($viewer)
            ->post(route('orders.discount.update', $order), ['discount_amount' => 20])
            ->assertForbidden();

        $actor = $this->makeResellerWithPermission([
            'orders.view',
            'orders.discount.update',
            'orders.status.update',
        ], $viewer->company);

        $this->actingAs($actor)
            ->post(route('orders.discount.update', $order), ['discount_amount' => 20])
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame('20.00', $order->fresh()->discount_amount);
        $this->assertSame('230.00', $order->fresh()->customer_payable_amount);

        $this->actingAs($actor)
            ->post(route('orders.discount.update', $order), ['discount_amount' => 999])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('discount_amount');

        $this->actingAs($actor)
            ->post(route('orders.status.update', $order), [
                'status' => OrderStatus::CONFIRMED->value,
            ])
            ->assertRedirect(route('orders.show', $order));

        $confirmed = $order->fresh();
        $this->assertNotNull($confirmed->discount_locked_at);

        $this->actingAs($actor)
            ->post(route('orders.discount.update', $order), ['discount_amount' => 5])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('discount_amount');

        $this->actingAs($actor)
            ->post(route('orders.status.update', $order), [
                'status' => OrderStatus::HOLD->value,
            ])
            ->assertRedirect(route('orders.show', $order));

        $this->assertNotNull($order->fresh()->discount_locked_at);

        $this->actingAs($actor)
            ->post(route('orders.discount.update', $order), ['discount_amount' => 5])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('discount_amount');

        $snapshotUnit = (string) $order->items()->first()->unit_selling_price;
        $variant->forceFill(['selling_price' => 999.00])->save();

        $this->actingAs($actor)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Discount locked');

        $this->assertSame($snapshotUnit, (string) $order->fresh(['items'])->items->first()->unit_selling_price);
        $this->assertSame('20.00', $order->fresh()->discount_amount);
        $this->assertSame('230.00', $order->fresh()->customer_payable_amount);
    }

    public function test_show_page_displays_operational_sections(): void
    {
        $actor = $this->makeResellerWithPermission([
            'orders.view',
            'orders.status.update',
            'orders.cca.assign',
            'orders.comments.create',
            'orders.discount.update',
        ]);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $order = $this->makeOrder($actor, $supplier, $this->makeVariant($supplier), [
            'customer_name_snapshot' => 'Ops Customer',
            'primary_phone_snapshot' => '0709988776',
        ]);

        $this->actingAs($actor)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Ops Customer')
            ->assertSee('0709988776')
            ->assertSee('Financial Summary')
            ->assertSee('Order Items')
            ->assertSee('CCA Assignment')
            ->assertSee('Comments / Activity')
            ->assertSee('Status History')
            ->assertSee('Shipment / Courier')
            ->assertSee('Change Status');
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

    private function makeCca(Company $resellerCompany): User
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

        return $user->fresh(['profile', 'role.portal']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(User $reseller, User $supplier, ProductVariant $variant, array $overrides = []): Order
    {
        $market = $this->marketByCode('lk');
        $customer = Customer::query()->create([
            'uuid' => UuidService::generate(),
            'display_name' => 'Workflow Customer',
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
            'customer_name_snapshot' => 'Workflow Customer',
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
}
