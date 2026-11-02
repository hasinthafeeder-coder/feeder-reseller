<?php

namespace Tests\Feature\Settlement;

use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Currency;
use Feeder\Core\Models\Market;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\ResellerSettlementBatch;
use Feeder\Core\Models\ResellerSettlementInvoice;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\User;
use Feeder\Core\Services\UuidService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

/**
 * Reseller portal isolation: a reseller may only view their own invoices.
 * Uses transactional access to the development database (rolled back).
 */
class ResellerSettlementInvoiceIsolationTest extends TestCase
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

    public function test_reseller_sees_only_own_invoices_and_cannot_open_another_resellers_invoice(): void
    {
        $currency = Currency::query()->where('iso_code', 'LKR')->firstOrFail();
        $market = Market::query()->where('code', 'lk')->firstOrFail();

        $ownerA = $this->makeResellerWithPermission(['dashboard.view']);
        $ownerB = $this->makeResellerWithPermission(['dashboard.view']);

        $batch = ResellerSettlementBatch::query()->create([
            'uuid' => UuidService::generate(),
            'batch_number' => 'RSB-ISO-'.Str::upper(Str::random(6)),
            'market_id' => $market->id,
            'market_code_snapshot' => 'LK',
            'timezone_snapshot' => 'Asia/Colombo',
            'settlement_date' => '2026-10-15',
            'kind' => ResellerSettlementBatch::KIND_DAILY,
            'sequence' => 90,
            'status' => ResellerSettlementBatch::STATUS_LOCKED,
            'generation_source' => ResellerSettlementBatch::GENERATION_SOURCE_MANUAL,
            'generated_by' => $ownerA->id,
            'generated_at' => now(),
            'locked_by' => $ownerA->id,
            'locked_at' => now(),
        ]);

        $ownInvoice = ResellerSettlementInvoice::query()->create([
            'uuid' => UuidService::generate(),
            'invoice_number' => 'RSI-OWN-'.Str::upper(Str::random(5)),
            'batch_id' => $batch->id,
            'reseller_id' => $ownerA->id,
            'reseller_company_id' => $ownerA->company_id,
            'reseller_name_snapshot' => 'Owner A',
            'currency_id' => $currency->id,
            'currency_code_snapshot' => 'LKR',
            'gross_earnings_total' => '120.00',
            'debt_deduction_total' => '20.00',
            'adjustment_total' => '0.00',
            'net_payable' => '100.00',
            'amount_paid' => '0.00',
            'outstanding_balance' => '100.00',
            'payment_status' => ResellerSettlementInvoice::PAYMENT_STATUS_UNPAID,
            'instruction_status' => ResellerSettlementInvoice::INSTRUCTION_STATUS_ACTIVE,
            'issued_at' => now(),
        ]);

        $otherInvoice = ResellerSettlementInvoice::query()->create([
            'uuid' => UuidService::generate(),
            'invoice_number' => 'RSI-OTHER-'.Str::upper(Str::random(5)),
            'batch_id' => $batch->id,
            'reseller_id' => $ownerB->id,
            'reseller_company_id' => $ownerB->company_id,
            'reseller_name_snapshot' => 'Owner B',
            'currency_id' => $currency->id,
            'currency_code_snapshot' => 'LKR',
            'gross_earnings_total' => '500.00',
            'debt_deduction_total' => '0.00',
            'adjustment_total' => '0.00',
            'net_payable' => '500.00',
            'amount_paid' => '0.00',
            'outstanding_balance' => '500.00',
            'payment_status' => ResellerSettlementInvoice::PAYMENT_STATUS_UNPAID,
            'instruction_status' => ResellerSettlementInvoice::INSTRUCTION_STATUS_ACTIVE,
            'issued_at' => now(),
        ]);

        $this->actingAs($ownerA)
            ->get(route('settlements.invoices.index'))
            ->assertOk()
            ->assertSee($ownInvoice->invoice_number)
            ->assertSee('LKR 100.00')
            ->assertDontSee($otherInvoice->invoice_number)
            ->assertDontSee('RSI-MOCK');

        $this->actingAs($ownerA)
            ->get(route('settlements.invoices.show', $ownInvoice))
            ->assertOk()
            ->assertSee($ownInvoice->invoice_number)
            ->assertSee('LKR 120.00')
            ->assertSee('LKR 20.00')
            ->assertSee('UNPAID');

        $this->actingAs($ownerA)
            ->get(route('settlements.invoices.print', $ownInvoice))
            ->assertOk()
            ->assertSee(number_format((float) $ownInvoice->net_payable, 2))
            ->assertSee('persisted invoice values');

        $this->actingAs($ownerA)
            ->get(route('settlements.invoices.show', $otherInvoice))
            ->assertNotFound();
    }

    public function test_settlement_invoices_require_dashboard_view_permission(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.view']);

        $this->actingAs($reseller)
            ->get(route('settlements.invoices.index'))
            ->assertForbidden();
    }

    /**
     * @param  list<string>  $permissionSlugs
     */
    private function makeResellerWithPermission(array $permissionSlugs): User
    {
        $portal = Portal::query()->firstOrCreate(
            ['code' => PortalCode::RESELLER->value],
            [
                'uuid' => UuidService::generate(),
                'name' => 'Reseller Portal',
                'subdomain' => 'reseller-'.Str::lower(Str::random(4)),
                'description' => 'Reseller Portal',
                'is_active' => true,
            ]
        );

        $company = Company::query()->create([
            'uuid' => UuidService::generate(),
            'portal_id' => $portal->id,
            'name' => 'Settlement Co '.Str::lower(Str::random(4)),
            'email' => 'settlement-'.Str::uuid().'@feeder.local',
            'phone' => '077'.random_int(100000, 999999),
            'registration_number' => 'REG-'.Str::random(6),
            'status' => CompanyStatus::ACTIVE->value,
        ]);

        $user = User::query()->create([
            'uuid' => UuidService::generate(),
            'company_id' => $company->id,
            'email' => $company->email,
            'phone' => $company->phone,
            'password' => Hash::make('password'),
            'user_type' => UserType::OWNER->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
        ]);

        $company->forceFill(['owner_user_id' => $user->id])->save();

        $role = Role::query()->firstOrCreate(
            [
                'portal_id' => $portal->id,
                'slug' => 'settlement-owner-'.Str::lower(Str::random(4)),
            ],
            [
                'uuid' => UuidService::generate(),
                'company_id' => null,
                'name' => 'Settlement Owner Test',
                'description' => 'Settlement Owner Test',
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
                    'uuid' => UuidService::generate(),
                    'module' => 'Dashboard',
                    'group' => 'Dashboard',
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
