<?php

namespace Tests\Feature\Order;

use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class ResellerOngoingOrdersUiTest extends TestCase
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

    public function test_ongoing_orders_page_loads_phase_2b_fulfillment_ui(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.view']);

        $this->actingAs($reseller)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee('Ongoing Orders')
            ->assertSee('Track orders currently moving through fulfillment and dispatch.')
            ->assertSee('id="resellerOngoingOrdersPage"', false)
            ->assertSee('Fulfillment tracking')
            ->assertSee('Ongoing Orders shows only PRINT, PRINTED, PACKING, and DISPATCHED.')
            ->assertSee('Call Center and Completed Orders handle pre-print and post-delivery workflows separately.')
            ->assertSee('Printing')
            ->assertSee('Printed')
            ->assertSee('Packing')
            ->assertSee('Dispatched')
            ->assertSee('PRINT')
            ->assertSee('PRINTED')
            ->assertSee('PACKING')
            ->assertSee('DISPATCHED')
            ->assertSee('Search order, customer, phone or waybill')
            ->assertSee('All Couriers')
            ->assertSee('All products')
            ->assertSee('Date From')
            ->assertSee('Date To')
            ->assertSee('data-orders=', false)
            ->assertDontSee('data-mock-orders', false)
            ->assertDontSee('Kasun Perera')
            ->assertDontSee('12610200123')
            ->assertDontSee('Royal Express')
            ->assertSee('No orders are currently in fulfillment.')
            ->assertSee('No orders are currently waiting for printing.')
            ->assertSee('No printed orders are waiting for packing.')
            ->assertSee('No orders are currently being packed.')
            ->assertSee('No dispatched orders are currently in progress.')
            ->assertSee('No ongoing orders match your filters.')
            ->assertDontSee('Phase 2B UI preview')
            ->assertDontSee('UI architecture preview')
            ->assertDontSee('example data')
            ->assertDontSee('mock preview')
            ->assertDontSee('Mark Delivered')
            ->assertDontSee('Mark Returned')
            ->assertDontSee('Payment Received')
            ->assertDontSee('Settle Payment')
            ->assertDontSee('Pending Approval')
            ->assertDontSee('1st Attempt')
            ->assertDontSee('2nd Attempt')
            ->assertDontSee('3rd Attempt')
            ->assertDontSee('DELIVERED')
            ->assertDontSee('RETURNED')
            ->assertDontSee('CANCELLED')
            ->assertDontSee('value="PENDING"', false)
            ->assertDontSee('value="CONFIRMED"', false)
            ->assertDontSee('value="HOLD"', false)
            ->assertDontSee('Mark Paid')
            ->assertDontSee('Receive Payment')
            ->assertDontSee('Create Payout')
            ->assertDontSee('Assign to Me')
            ->assertDontSee('Pull from Pool');
    }

    public function test_ongoing_orders_requires_orders_view_permission(): void
    {
        $reseller = $this->makeResellerWithPermission(['products.view']);

        $this->actingAs($reseller)
            ->get(route('orders.index'))
            ->assertForbidden();
    }

    public function test_call_center_workspace_remains_separate_from_ongoing_ui(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.create']);

        $this->actingAs($reseller)
            ->get(route('orders.index', ['workspace' => 'call-center']))
            ->assertOk()
            ->assertSee('Call Center Orders')
            ->assertDontSee('id="resellerOngoingOrdersPage"', false)
            ->assertDontSee('Track orders currently moving through fulfillment and dispatch.');
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
            'email' => $company->email,
            'phone' => $company->phone,
            'password' => Hash::make('password'),
            'user_type' => UserType::OWNER->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
        ]);

        $company->forceFill(['owner_user_id' => $user->id])->save();
        $this->configureResellerCompany($company, ['lk']);

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
}
