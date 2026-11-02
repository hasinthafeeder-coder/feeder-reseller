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

class ResellerCompletedOrdersUiTest extends TestCase
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

    public function test_completed_orders_page_loads_phase_2b_outcome_ui(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.view']);

        $this->actingAs($reseller)
            ->get(route('orders.index', ['workspace' => 'archived']))
            ->assertOk()
            ->assertSee('Completed Orders')
            ->assertSee('View delivered and returned orders and their reseller settlement status.')
            ->assertSee('id="resellerCompletedOrdersPage"', false)
            ->assertSee('Successfully delivered orders')
            ->assertSee('Orders returned after dispatch')
            ->assertSee('Orders awaiting reseller settlement')
            ->assertSee('Reseller settlement recorded')
            ->assertSee('Search order, customer, phone, waybill or invoice')
            ->assertSee('Settlement')
            ->assertSee('Settlement Pending')
            ->assertSee('Settled')
            ->assertSee('Reseller settlement for delivered and returned orders.')
            ->assertSee('Completed Orders contains the recent completed-order window.')
            ->assertSee('Older completed/settled history will appear under Archived Orders.')
            ->assertSee('All Couriers')
            ->assertSee('Date From')
            ->assertSee('Date To')
            ->assertSee('data-orders=', false)
            ->assertDontSee('data-mock-orders', false)
            ->assertDontSee('Kasun Perera')
            ->assertDontSee('12610200123')
            ->assertDontSee('Royal Express')
            ->assertSee('DELIVERED')
            ->assertSee('RETURNED')
            ->assertSee('No delivered or returned orders yet.')
            ->assertSee('No delivered orders found.')
            ->assertSee('No returned orders found.')
            ->assertSee('No orders are currently settlement pending.')
            ->assertSee('No settled orders found.')
            ->assertSee('No completed orders match your filters.')
            ->assertSee('Reseller settlement is handled separately by Admin.')
            ->assertSee('Returned orders may include a reseller settlement adjustment.')
            ->assertSee('do not mean every return creates a financial loss.')
            ->assertSee('Settlement/Adjustment')
            ->assertSee('No courier payment for returned orders')
            ->assertSee('>PLUS<', false)
            ->assertSee('>MINUS<', false)
            ->assertDontSee('UI architecture preview')
            ->assertDontSee('Expired Orders')
            ->assertSee('Archived Orders')
            ->assertDontSee('Mark Paid')
            ->assertDontSee('Settle Payment')
            ->assertDontSee('Mark Delivered')
            ->assertDontSee('Mark Returned')
            ->assertDontSee('Receive Payment')
            ->assertDontSee('Pay Supplier')
            ->assertDontSee('Create Payout')
            ->assertDontSee('Adjust Payment')
            ->assertDontSee('id="resellerOngoingOrdersPage"', false);
    }

    public function test_completed_orders_uses_reseller_settlement_terminology(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.view']);

        $content = $this->actingAs($reseller)
            ->get(route('orders.index', ['workspace' => 'archived']))
            ->assertOk()
            ->getContent();

        $markup = $this->completedOrdersUiMarkup($content);

        $this->assertStringContainsString('DELIVERED', $markup);
        $this->assertStringContainsString('RETURNED', $markup);
        $this->assertStringContainsString('value="DELIVERED"', $markup);
        $this->assertStringContainsString('value="RETURNED"', $markup);
        $this->assertStringContainsString('Settlement Pending', $markup);
        $this->assertStringContainsString('Settled', $markup);
        $this->assertStringContainsString('value="Settlement Pending"', $markup);
        $this->assertStringContainsString('value="Settled"', $markup);
        $this->assertStringContainsString('>Settlement<', $markup);
        $this->assertStringContainsString('Reseller Settlement', $markup);
        $this->assertStringContainsString('settlement_status', $markup);
        $this->assertStringContainsString('is-settlement-pending', $markup);
        $this->assertStringContainsString('is-settled', $markup);
        $this->assertStringContainsString('is-settlement-adjustment', $markup);
        $this->assertStringContainsString('Settlement/Adjustment', $markup);
        $this->assertStringContainsString('No courier payment for returned orders', $markup);
        $this->assertStringContainsString('Completed Orders contains the recent completed-order window.', $markup);
        $this->assertStringContainsString('Older completed/settled history will appear under Archived Orders.', $markup);
        $this->assertStringContainsString('Archived Orders', $markup);
        $this->assertStringContainsString('order-view-btn', $markup);
        $this->assertStringContainsString('>PLUS<', $markup);
        $this->assertStringContainsString('>MINUS<', $markup);

        $this->assertStringNotContainsString('Payment Pending', $markup);
        $this->assertStringNotContainsString('Payment Received', $markup);
        $this->assertStringNotContainsString('Payment Status', $markup);
        $this->assertStringNotContainsString('Payment Source', $markup);
        $this->assertStringNotContainsString('>Payment<', $markup);
        $this->assertStringNotContainsString('payment_source', $markup);
        $this->assertStringNotContainsString('payment_status', $markup);
        $this->assertStringNotContainsString('courier reconciliation', $markup);
        $this->assertStringNotContainsString('Payments reconciled', $markup);
        $this->assertStringNotContainsString('is-payment-pending', $markup);
        $this->assertStringNotContainsString('is-payment-received', $markup);
        $this->assertStringNotContainsString('is-payment-paid', $markup);
        $this->assertStringNotContainsString('value="PENDING"', $markup);
        $this->assertStringNotContainsString('value="RECEIVED"', $markup);
        $this->assertStringNotContainsString('value="PAID"', $markup);
        $this->assertStringNotContainsString('>PENDING<', $markup);
        $this->assertStringNotContainsString('>RECEIVED<', $markup);
        $this->assertStringNotContainsString('>PAID<', $markup);
        $this->assertStringNotContainsString('Mark Paid', $markup);
        $this->assertStringNotContainsString('Settle Payment', $markup);
        $this->assertStringNotContainsString('Mark Delivered', $markup);
        $this->assertStringNotContainsString('Mark Returned', $markup);

        foreach (['PENDING', 'RECEIVED', 'PAID'] as $token) {
            $this->assertDoesNotMatchRegularExpression(
                '/(?<![A-Za-z_])'.$token.'(?![A-Za-z_])/',
                $markup,
                $token.' must not appear as a completed-order label or value.'
            );
        }
    }

    public function test_completed_orders_requires_orders_view_permission(): void
    {
        $reseller = $this->makeResellerWithPermission(['products.view']);

        $this->actingAs($reseller)
            ->get(route('orders.index', ['workspace' => 'archived']))
            ->assertForbidden();
    }

    public function test_ongoing_and_call_center_remain_separate_from_completed_ui(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.view', 'orders.cca.assign', 'orders.create']);

        $this->actingAs($reseller)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee('id="resellerOngoingOrdersPage"', false)
            ->assertDontSee('id="resellerCompletedOrdersPage"', false);

        $this->actingAs($reseller)
            ->get(route('orders.index', ['workspace' => 'call-center']))
            ->assertOk()
            ->assertSee('Call Center Orders')
            ->assertDontSee('id="resellerCompletedOrdersPage"', false)
            ->assertDontSee('View delivered and returned orders and their reseller settlement status.');
    }

    /**
     * Completed Orders markup, its page script, and the settlement styles.
     * Lifecycle tokens are checked here so unrelated layout text cannot cause a false failure.
     */
    private function completedOrdersUiMarkup(string $html): string
    {
        $mainStart = strpos($html, '<main>');
        $mainEnd = strpos($html, '</main>');
        $this->assertNotFalse($mainStart);
        $this->assertNotFalse($mainEnd);
        $main = substr($html, $mainStart, ($mainEnd + strlen('</main>')) - $mainStart);

        $scriptMarker = "document.getElementById('resellerCompletedOrdersPage')";
        $scriptPos = strpos($html, $scriptMarker);
        $this->assertNotFalse($scriptPos);
        $scriptOpen = strrpos(substr($html, 0, $scriptPos), '<script');
        $scriptClose = strpos($html, '</script>', $scriptPos);
        $this->assertNotFalse($scriptOpen);
        $this->assertNotFalse($scriptClose);
        $script = substr($html, $scriptOpen, ($scriptClose + strlen('</script>')) - $scriptOpen);

        $styleMarker = '.badge-status.is-settlement-pending';
        $stylePos = strpos($html, $styleMarker);
        $this->assertNotFalse($stylePos);
        $styleEnd = strpos($html, '.completed-advanced-filters', $stylePos);
        $this->assertNotFalse($styleEnd);
        $styles = substr($html, $stylePos, $styleEnd - $stylePos);

        return $main."\n".$script."\n".$styles;
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
