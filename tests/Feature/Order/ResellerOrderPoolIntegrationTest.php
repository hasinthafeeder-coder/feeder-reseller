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

class ResellerOrderPoolIntegrationTest extends TestCase
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

    public function test_manual_reseller_send_to_pool_direct_and_unassigned(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);
        $cca = $this->makeCca($reseller->company, ['orders.create', 'orders.view', 'orders.update']);

        $this->actingAs($reseller)->post(route('orders.store'), $this->payload($supplier, $variant, [
            'intent' => 'send_to_call_center',
            'assignment_target' => 'pool',
        ]))->assertRedirect();

        $poolOrder = Order::query()->where('reseller_company_id', $reseller->company_id)->latest('id')->first();
        $this->assertSame(OrderAssignmentState::POOL, $poolOrder->assignmentState());
        $this->assertSame(OrderStatus::PENDING, $poolOrder->status);

        $this->actingAs($reseller)->post(route('orders.store'), $this->payload($supplier, $variant, [
            'intent' => 'send_to_call_center',
            'assignment_target' => 'cca',
            'assign_cca_id' => $cca->id,
            'primary_phone' => '070'.random_int(1000000, 9999999),
        ]))->assertRedirect();

        $assigned = Order::query()->where('reseller_company_id', $reseller->company_id)->latest('id')->first();
        $this->assertSame(OrderAssignmentState::ASSIGNED, $assigned->assignmentState());
        $this->assertSame((int) $cca->id, (int) $assigned->cca_id);
        $this->assertSame(
            OrderCcaAssignmentOrigin::DIRECT,
            OrderCcaAssignment::query()->where('order_id', $assigned->id)->latest('id')->first()->origin
        );

        $this->actingAs($reseller)->post(route('orders.store'), $this->payload($supplier, $variant, [
            'intent' => 'send_to_call_center',
            'assignment_target' => 'unassigned',
            'primary_phone' => '070'.random_int(1000000, 9999999),
        ]))->assertRedirect();

        $unassigned = Order::query()->where('reseller_company_id', $reseller->company_id)->latest('id')->first();
        $this->assertSame(OrderAssignmentState::UNASSIGNED, $unassigned->assignmentState());
    }

    public function test_manual_cca_create_auto_assigns_with_manual_create_origin(): void
    {
        $owner = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $cca = $this->makeCca($owner->company, ['orders.create', 'orders.view', 'orders.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($owner, $supplier);
        $variant = $this->makeVariant($supplier);

        $this->actingAs($cca)->post(route('orders.store'), $this->payload($supplier, $variant, [
            'intent' => 'confirm',
        ]))->assertRedirect();

        $order = Order::query()->where('reseller_company_id', $owner->company_id)->latest('id')->first();
        $this->assertSame((int) $cca->id, (int) $order->cca_id);
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertFalse((bool) $order->available_in_pool);
        $this->assertSame(
            OrderCcaAssignmentOrigin::MANUAL_CREATE,
            OrderCcaAssignment::query()->where('order_id', $order->id)->latest('id')->first()->origin
        );
    }

    public function test_index_bootstrap_counts_filters_and_cca_company_visibility(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.update']);
        $cca = $this->makeCca($reseller->company, ['orders.view', 'orders.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);

        $assigned = $this->makeOrder($reseller, $supplier, $variant, ['order_number' => 'ORD-ASSIGNED-1']);
        $pool = $this->makeOrder($reseller, $supplier, $variant, ['order_number' => 'ORD-POOL-1']);
        $this->makeOrder($reseller, $supplier, $variant, ['order_number' => 'ORD-UNASSIGNED-1']);

        app(OrderCcaAssignmentService::class)->assign($assigned, (int) $cca->id, (int) $reseller->id);
        app(OrderCcaAssignmentService::class)->moveToPool($pool, (int) $reseller->id);

        $response = $this->actingAs($cca)
            ->getJson(route('orders.index', ['json' => 1]))
            ->assertOk();

        $counts = $response->json('data.counts');
        $this->assertSame(3, $counts['all']);
        $this->assertSame(1, $counts['assigned']);
        $this->assertSame(1, $counts['unassigned']);
        $this->assertSame(1, $counts['pool']);

        $statusCounts = $response->json('data.status_counts');
        $this->assertSame(3, $statusCounts['all']);
        $this->assertSame(3, $statusCounts['PENDING']);
        $this->assertSame(0, $statusCounts['CONFIRMED']);

        $this->actingAs($cca)
            ->get(route('orders.index', ['tab' => 'company']))
            ->assertOk()
            ->assertSee('ORD-ASSIGNED-1')
            ->assertSee('ORD-POOL-1')
            ->assertSee('ORD-UNASSIGNED-1');

        $this->actingAs($reseller)
            ->getJson(route('orders.index', ['json' => 1, 'assignment' => 'pool']))
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonFragment(['orderNumber' => 'ORD-POOL-1']);
    }

    public function test_bulk_assign_and_bulk_pool_and_claim(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.update']);
        $cca = $this->makeCca($reseller->company, ['orders.view', 'orders.update']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier);

        $one = $this->makeOrder($reseller, $supplier, $variant);
        $two = $this->makeOrder($reseller, $supplier, $variant);

        $this->actingAs($reseller)
            ->postJson(route('orders.bulk.pool'), ['order_ids' => [$one->id, $two->id]])
            ->assertOk();

        $this->assertSame(OrderAssignmentState::POOL, $one->fresh()->assignmentState());

        $this->actingAs($cca)
            ->postJson(route('orders.cca.claim', $one))
            ->assertOk();

        $this->assertSame((int) $cca->id, (int) $one->fresh()->cca_id);
        $this->assertSame(
            OrderCcaAssignmentOrigin::POOL_CLAIM,
            OrderCcaAssignment::query()->where('order_id', $one->id)->latest('id')->first()->origin
        );

        $this->actingAs($reseller)
            ->postJson(route('orders.bulk.assign'), [
                'order_ids' => [$two->id],
                'cca_id' => $cca->id,
            ])
            ->assertOk();

        $this->assertSame(OrderAssignmentState::ASSIGNED, $two->fresh()->assignmentState());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(User $supplier, ProductVariant $variant, array $overrides = []): array
    {
        return array_merge([
            'market_id' => $this->marketByCode('lk')->id,
            'supplier_id' => $supplier->id,
            'customer_name' => 'Pool Integration Customer',
            'primary_phone' => '070'.random_int(1000000, 9999999),
            'address_line1' => '12 Test Street',
            'city_name' => 'Colombo',
            'district_name' => 'Colombo',
            'items' => [
                ['product_variant_id' => $variant->id, 'quantity' => 1],
            ],
            'discount_amount' => 0,
        ], $overrides);
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
        }

        // Keep CCA role for eligibility; attach extra role permissions via direct permissions.
        foreach ($permissionSlugs as $slug) {
            $permission = Permission::query()
                ->where('portal_id', $portal->id)
                ->where('slug', $slug)
                ->first();
            if ($permission) {
                $user->directPermissions()->syncWithoutDetaching([
                    $permission->id => ['allowed' => true],
                ]);
            }
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
