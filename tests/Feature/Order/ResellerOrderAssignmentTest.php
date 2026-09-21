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

class ResellerOrderAssignmentTest extends TestCase
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

    public function test_owner_can_assign_one_unassigned_order(): void
    {
        [$reseller, $cca, $supplier, $variant] = $this->makeAssignmentContext();
        $order = $this->makeOrder($reseller, $supplier, $variant);

        $this->actingAs($reseller)
            ->postJson(route('orders.bulk.assign'), [
                'order_ids' => [$order->id],
                'cca_id' => $cca->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned_count', 1);

        $order->refresh();
        $this->assertSame((int) $cca->id, (int) $order->cca_id);
        $this->assertFalse((bool) $order->available_in_pool);
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame(OrderAssignmentState::ASSIGNED, $order->assignmentState());
        $this->assertSame(
            OrderCcaAssignmentOrigin::DIRECT,
            OrderCcaAssignment::query()->where('order_id', $order->id)->latest('id')->first()->origin
        );
    }

    public function test_owner_can_assign_multiple_unassigned_orders(): void
    {
        [$reseller, $cca, $supplier, $variant] = $this->makeAssignmentContext();
        $one = $this->makeOrder($reseller, $supplier, $variant);
        $two = $this->makeOrder($reseller, $supplier, $variant);

        $this->actingAs($reseller)
            ->postJson(route('orders.bulk.assign'), [
                'order_ids' => [$one->id, $two->id],
                'cca_id' => $cca->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned_count', 2);

        $this->assertSame((int) $cca->id, (int) $one->fresh()->cca_id);
        $this->assertSame((int) $cca->id, (int) $two->fresh()->cca_id);
        $this->assertFalse((bool) $one->fresh()->available_in_pool);
        $this->assertFalse((bool) $two->fresh()->available_in_pool);
        $this->assertSame(2, OrderCcaAssignment::query()->whereIn('order_id', [$one->id, $two->id])->whereNull('unassigned_at')->count());
    }

    public function test_already_assigned_orders_cannot_be_silently_reassigned(): void
    {
        [$reseller, $cca, $supplier, $variant] = $this->makeAssignmentContext();
        $otherCca = $this->makeCca($reseller->company, ['orders.view', 'orders.update']);
        $assigned = $this->makeOrder($reseller, $supplier, $variant);
        $unassigned = $this->makeOrder($reseller, $supplier, $variant);

        app(OrderCcaAssignmentService::class)->assign($assigned, (int) $cca->id, (int) $reseller->id);

        $this->actingAs($reseller)
            ->postJson(route('orders.bulk.assign'), [
                'order_ids' => [$assigned->id, $unassigned->id],
                'cca_id' => $otherCca->id,
            ])
            ->assertStatus(422);

        $this->assertSame((int) $cca->id, (int) $assigned->fresh()->cca_id);
        $this->assertNull($unassigned->fresh()->cca_id);
    }

    public function test_random_quantity_assigns_exactly_n_when_enough_exist(): void
    {
        [$reseller, $cca, $supplier, $variant] = $this->makeAssignmentContext();
        for ($i = 0; $i < 5; $i++) {
            $this->makeOrder($reseller, $supplier, $variant);
        }

        $this->actingAs($reseller)
            ->postJson(route('orders.bulk.assign-random'), [
                'cca_id' => $cca->id,
                'quantity' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('data.requested', 3)
            ->assertJsonPath('data.assigned_count', 3);

        $this->assertSame(3, Order::query()
            ->where('reseller_company_id', $reseller->company_id)
            ->where('cca_id', $cca->id)
            ->count());
        $this->assertSame(2, Order::query()
            ->where('reseller_company_id', $reseller->company_id)
            ->assignmentState(OrderAssignmentState::UNASSIGNED)
            ->count());
    }

    public function test_random_allocations_assign_requested_quantities_across_ccas(): void
    {
        [$reseller, $cca, $supplier, $variant] = $this->makeAssignmentContext();
        $secondCca = $this->makeCca($reseller->company, ['orders.view', 'orders.update']);

        for ($i = 0; $i < 7; $i++) {
            $this->makeOrder($reseller, $supplier, $variant);
        }

        $this->actingAs($reseller)
            ->postJson(route('orders.bulk.assign-random-allocations'), [
                'allocations' => [
                    ['cca_id' => $cca->id, 'quantity' => 3],
                    ['cca_id' => $secondCca->id, 'quantity' => 2],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.requested', 5)
            ->assertJsonPath('data.assigned_count', 5);

        $this->assertSame(3, Order::query()
            ->where('reseller_company_id', $reseller->company_id)
            ->where('cca_id', $cca->id)
            ->count());
        $this->assertSame(2, Order::query()
            ->where('reseller_company_id', $reseller->company_id)
            ->where('cca_id', $secondCca->id)
            ->count());
        $this->assertSame(2, Order::query()
            ->where('reseller_company_id', $reseller->company_id)
            ->assignmentState(OrderAssignmentState::UNASSIGNED)
            ->count());
    }

    public function test_random_allocations_reject_when_total_exceeds_remaining(): void
    {
        [$reseller, $cca, $supplier, $variant] = $this->makeAssignmentContext();
        $secondCca = $this->makeCca($reseller->company, ['orders.view', 'orders.update']);
        $this->makeOrder($reseller, $supplier, $variant);
        $this->makeOrder($reseller, $supplier, $variant);

        $this->actingAs($reseller)
            ->postJson(route('orders.bulk.assign-random-allocations'), [
                'allocations' => [
                    ['cca_id' => $cca->id, 'quantity' => 2],
                    ['cca_id' => $secondCca->id, 'quantity' => 2],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['allocations']);

        $this->assertSame(2, Order::query()
            ->where('reseller_company_id', $reseller->company_id)
            ->assignmentState(OrderAssignmentState::UNASSIGNED)
            ->count());
    }

    public function test_random_quantity_assigns_only_remaining_when_fewer_exist(): void
    {
        [$reseller, $cca, $supplier, $variant] = $this->makeAssignmentContext();
        $this->makeOrder($reseller, $supplier, $variant);
        $this->makeOrder($reseller, $supplier, $variant);

        $response = $this->actingAs($reseller)
            ->postJson(route('orders.bulk.assign-random'), [
                'cca_id' => $cca->id,
                'quantity' => 20,
            ])
            ->assertOk();

        $this->assertSame(20, $response->json('data.requested'));
        $this->assertSame(2, $response->json('data.assigned_count'));
        $this->assertStringContainsString('fewer eligible', (string) $response->json('message'));
        $this->assertSame(2, Order::query()
            ->where('reseller_company_id', $reseller->company_id)
            ->where('cca_id', $cca->id)
            ->count());
    }

    public function test_random_never_selects_already_assigned_or_other_company_orders(): void
    {
        [$reseller, $cca, $supplier, $variant] = $this->makeAssignmentContext();
        [$otherReseller, , $otherSupplier, $otherVariant] = $this->makeAssignmentContext();

        $assigned = $this->makeOrder($reseller, $supplier, $variant);
        app(OrderCcaAssignmentService::class)->assign($assigned, (int) $cca->id, (int) $reseller->id);

        $foreign = $this->makeOrder($otherReseller, $otherSupplier, $otherVariant);
        $eligible = $this->makeOrder($reseller, $supplier, $variant);

        $this->actingAs($reseller)
            ->postJson(route('orders.bulk.assign-random'), [
                'cca_id' => $cca->id,
                'quantity' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned_count', 1);

        $this->assertSame((int) $cca->id, (int) $eligible->fresh()->cca_id);
        $this->assertSame((int) $cca->id, (int) $assigned->fresh()->cca_id);
        $this->assertNull($foreign->fresh()->cca_id);
    }

    public function test_selected_unassigned_orders_can_be_sent_to_pool(): void
    {
        [$reseller, $cca, $supplier, $variant] = $this->makeAssignmentContext();
        $one = $this->makeOrder($reseller, $supplier, $variant);
        $two = $this->makeOrder($reseller, $supplier, $variant);

        $this->actingAs($reseller)
            ->postJson(route('orders.bulk.pool'), [
                'order_ids' => [$one->id, $two->id],
            ])
            ->assertOk()
            ->assertJsonPath('data.assigned_count', 2);

        foreach ([$one, $two] as $order) {
            $fresh = $order->fresh();
            $this->assertNull($fresh->cca_id);
            $this->assertTrue((bool) $fresh->available_in_pool);
            $this->assertSame(OrderAssignmentState::POOL, $fresh->assignmentState());
            $this->assertSame(OrderStatus::PENDING, $fresh->status);
        }

        $this->assertNotNull($cca->id);
    }

    public function test_already_assigned_orders_cannot_be_moved_to_pool(): void
    {
        [$reseller, $cca, $supplier, $variant] = $this->makeAssignmentContext();
        $assigned = $this->makeOrder($reseller, $supplier, $variant);
        $unassigned = $this->makeOrder($reseller, $supplier, $variant);

        app(OrderCcaAssignmentService::class)->assign($assigned, (int) $cca->id, (int) $reseller->id);

        $this->actingAs($reseller)
            ->postJson(route('orders.bulk.pool'), [
                'order_ids' => [$assigned->id, $unassigned->id],
            ])
            ->assertStatus(422);

        $this->assertSame((int) $cca->id, (int) $assigned->fresh()->cca_id);
        $this->assertFalse((bool) $assigned->fresh()->available_in_pool);
        $this->assertSame(OrderAssignmentState::UNASSIGNED, $unassigned->fresh()->assignmentState());
    }

    public function test_inactive_and_foreign_and_invalid_cca_are_rejected(): void
    {
        [$reseller, $cca, $supplier, $variant] = $this->makeAssignmentContext();
        [$otherReseller] = $this->makeAssignmentContext();
        $foreignCca = $this->makeCca($otherReseller->company, ['orders.view', 'orders.update']);
        $inactive = $this->makeCca($reseller->company, ['orders.view', 'orders.update']);
        $inactive->forceFill(['status' => UserStatus::SUSPENDED->value])->save();
        $order = $this->makeOrder($reseller, $supplier, $variant);

        $this->actingAs($reseller)
            ->postJson(route('orders.bulk.assign'), [
                'order_ids' => [$order->id],
                'cca_id' => $inactive->id,
            ])
            ->assertStatus(422);

        $this->actingAs($reseller)
            ->postJson(route('orders.bulk.assign'), [
                'order_ids' => [$order->id],
                'cca_id' => $foreignCca->id,
            ])
            ->assertStatus(422);

        $this->actingAs($reseller)
            ->postJson(route('orders.bulk.assign'), [
                'order_ids' => [$order->id],
                'cca_id' => 999999999,
            ])
            ->assertStatus(422);

        $this->assertNull($order->fresh()->cca_id);
        $this->assertNotNull($cca->id);
    }

    public function test_unauthorized_users_and_cross_company_order_ids_are_rejected(): void
    {
        [$reseller, $cca, $supplier, $variant] = $this->makeAssignmentContext();
        [$otherReseller, , $otherSupplier, $otherVariant] = $this->makeAssignmentContext();
        $viewer = $this->makeResellerWithPermission(['orders.view']);
        $order = $this->makeOrder($reseller, $supplier, $variant);
        $foreign = $this->makeOrder($otherReseller, $otherSupplier, $otherVariant);

        $this->actingAs($viewer)
            ->postJson(route('orders.bulk.assign'), [
                'order_ids' => [$order->id],
                'cca_id' => $cca->id,
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->postJson(route('orders.bulk.assign-random'), [
                'cca_id' => $cca->id,
                'quantity' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->postJson(route('orders.bulk.pool'), [
                'order_ids' => [$order->id],
            ])
            ->assertForbidden();

        $this->actingAs($reseller)
            ->postJson(route('orders.bulk.assign'), [
                'order_ids' => [$foreign->id],
                'cca_id' => $cca->id,
            ])
            ->assertStatus(422);

        $this->assertNull($foreign->fresh()->cca_id);
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: ProductVariant}
     */
    private function makeAssignmentContext(): array
    {
        $reseller = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.update']);
        $cca = $this->makeCca($reseller->company, ['orders.view', 'orders.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);

        return [$reseller, $cca, $supplier, $variant];
    }

    /**
     * @param  list<string>  $permissionSlugs
     */
    private function makeResellerWithPermission(array $permissionSlugs): User
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
