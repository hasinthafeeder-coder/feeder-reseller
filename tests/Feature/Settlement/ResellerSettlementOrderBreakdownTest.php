<?php

namespace Tests\Feature\Settlement;

use App\Services\Settlement\ResellerSettlementInvoiceReadService;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\OrderSource;
use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Currency;
use Feeder\Core\Models\Customer;
use Feeder\Core\Models\Market;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Portal;
use Feeder\Core\Enums\ResellerDebtObligationType;
use Feeder\Core\Enums\ResellerReturnReason;
use Feeder\Core\Models\ResellerDebtObligation;
use Feeder\Core\Models\ResellerEarningObligation;
use Feeder\Core\Models\ResellerSettlementBatch;
use Feeder\Core\Models\ResellerSettlementDebtAllocation;
use Feeder\Core\Models\ResellerSettlementInvoice;
use Feeder\Core\Models\ResellerSettlementInvoiceLine;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\User;
use Feeder\Core\Services\UuidService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

/**
 * Reseller invoice order-wise breakdown against the development MySQL database.
 * Transactional; rolled back after each test.
 */
class ResellerSettlementOrderBreakdownTest extends TestCase
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

    public function test_order_breakdown_uses_persisted_snapshots_and_blocks_foreign_invoices(): void
    {
        $currency = Currency::query()->where('iso_code', 'LKR')->firstOrFail();
        $market = Market::query()->where('code', 'lk')->firstOrFail();
        $owner = $this->makeResellerWithPermission(['dashboard.view']);
        $stranger = $this->makeResellerWithPermission(['dashboard.view']);

        $batch = ResellerSettlementBatch::query()->create([
            'uuid' => UuidService::generate(),
            'batch_number' => 'RSB-ORD-'.Str::upper(Str::random(5)),
            'market_id' => $market->id,
            'market_code_snapshot' => 'LK',
            'timezone_snapshot' => 'Asia/Colombo',
            'settlement_date' => '2026-10-15',
            'kind' => ResellerSettlementBatch::KIND_DAILY,
            'sequence' => 91,
            'status' => ResellerSettlementBatch::STATUS_LOCKED,
            'generation_source' => ResellerSettlementBatch::GENERATION_SOURCE_MANUAL,
            'generated_by' => $owner->id,
            'generated_at' => now(),
            'locked_by' => $owner->id,
            'locked_at' => now(),
        ]);

        $ownInvoice = ResellerSettlementInvoice::query()->create([
            'uuid' => UuidService::generate(),
            'invoice_number' => 'RSI-ORD-'.Str::upper(Str::random(5)),
            'batch_id' => $batch->id,
            'reseller_id' => $owner->id,
            'reseller_company_id' => $owner->company_id,
            'reseller_name_snapshot' => 'Owner',
            'currency_id' => $currency->id,
            'currency_code_snapshot' => 'LKR',
            'gross_earnings_total' => '280.00',
            'debt_deduction_total' => '25.00',
            'adjustment_total' => '0.00',
            'net_payable' => '255.00',
            'amount_paid' => '0.00',
            'outstanding_balance' => '255.00',
            'payment_status' => ResellerSettlementInvoice::PAYMENT_STATUS_UNPAID,
            'instruction_status' => ResellerSettlementInvoice::INSTRUCTION_STATUS_ACTIVE,
            'issued_at' => now(),
        ]);

        $otherInvoice = ResellerSettlementInvoice::query()->create([
            'uuid' => UuidService::generate(),
            'invoice_number' => 'RSI-FRN-'.Str::upper(Str::random(5)),
            'batch_id' => $batch->id,
            'reseller_id' => $stranger->id,
            'reseller_company_id' => $stranger->company_id,
            'reseller_name_snapshot' => 'Stranger',
            'currency_id' => $currency->id,
            'currency_code_snapshot' => 'LKR',
            'gross_earnings_total' => '999.00',
            'debt_deduction_total' => '0.00',
            'adjustment_total' => '0.00',
            'net_payable' => '999.00',
            'amount_paid' => '0.00',
            'outstanding_balance' => '999.00',
            'payment_status' => ResellerSettlementInvoice::PAYMENT_STATUS_UNPAID,
            'instruction_status' => ResellerSettlementInvoice::INSTRUCTION_STATUS_ACTIVE,
            'issued_at' => now(),
        ]);

        $customer = Customer::query()->create([
            'uuid' => UuidService::generate(),
            'display_name' => 'Breakdown Customer',
            'primary_country_id' => $market->country_id,
            'is_banned' => false,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $order = Order::query()->create([
            'uuid' => UuidService::generate(),
            'order_number' => 'ORD-BRK-'.Str::upper(Str::random(4)),
            'source' => OrderSource::MANUAL,
            'status' => OrderStatus::DELIVERED,
            'market_id' => $market->id,
            'currency_id' => $currency->id,
            'market_code_snapshot' => 'lk',
            'currency_code_snapshot' => 'LKR',
            'reseller_id' => $owner->id,
            'reseller_company_id' => $owner->company_id,
            'supplier_id' => $owner->id,
            'customer_id' => $customer->id,
            'customer_name_snapshot' => 'Breakdown Customer',
            'primary_phone_snapshot' => '070'.random_int(1000000, 9999999),
            'primary_phone_country_id' => $market->country_id,
            'delivered_at' => '2026-10-10 09:15:00',
            'items_subtotal' => '1000.00',
            'discount_amount' => '0.00',
            'courier_fee_amount' => '350.00',
            'customer_payable_amount' => '1000.00',
            'total_weight' => '0.500',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $obligation = ResellerEarningObligation::query()->create([
            'uuid' => UuidService::generate(),
            'obligation_number' => 'REO-'.Str::upper(Str::random(6)),
            'reseller_id' => $owner->id,
            'reseller_company_id' => $owner->company_id,
            'order_id' => $order->id,
            'market_id' => $market->id,
            'currency_id' => $currency->id,
            'currency_code_snapshot' => 'LKR',
            'business_date' => '2026-10-10',
            'cod_amount_snapshot' => '1000.00',
            'supplier_cost_snapshot' => '280.00',
            'service_charge_snapshot' => '40.00',
            'courier_charge_snapshot' => '350.00',
            'company_commission_snapshot' => '50.00',
            'gross_reseller_profit' => '280.00',
            'source_type' => ResellerEarningObligation::SOURCE_COURIER_RECEIPT_CONFIRMATION,
            'source_idempotency_key' => 'test-'.Str::uuid(),
        ]);

        ResellerSettlementInvoiceLine::query()->create([
            'uuid' => UuidService::generate(),
            'invoice_id' => $ownInvoice->id,
            'earning_obligation_id' => $obligation->id,
            'settlement_date' => '2026-10-15',
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'obligation_number_snapshot' => $obligation->obligation_number,
            'business_date_snapshot' => '2026-10-10',
            'cod_amount_snapshot' => '1000.00',
            'service_charge_snapshot' => '40.00',
            'courier_charge_snapshot' => '350.00',
            'company_commission_snapshot' => '50.00',
            'gross_reseller_profit' => '280.00',
            'currency_id' => $currency->id,
            'currency_code_snapshot' => 'LKR',
            'line_payment_status' => ResellerSettlementInvoiceLine::LINE_PAYMENT_STATUS_UNPAID,
        ]);

        $debt = ResellerDebtObligation::query()->create([
            'uuid' => UuidService::generate(),
            'obligation_number' => 'RDO-BRK-'.Str::upper(Str::random(4)),
            'reseller_id' => $owner->id,
            'reseller_company_id' => $owner->company_id,
            'order_id' => $order->id,
            'market_id' => $market->id,
            'currency_id' => $currency->id,
            'currency_code_snapshot' => 'LKR',
            'obligation_type' => ResellerDebtObligationType::RETURN_FINE,
            'return_reason' => ResellerReturnReason::DUPLICATE,
            'original_amount' => '25.00',
            'outstanding_amount' => '25.00',
            'effective_date' => '2026-10-12',
            'source_type' => ResellerDebtObligation::SOURCE_SUPPLIER_RETURN_SCAN,
            'source_idempotency_key' => 'return_fine:order:'.$order->id.':'.Str::uuid(),
            'recorded_by' => $owner->id,
        ]);

        ResellerSettlementDebtAllocation::query()->create([
            'uuid' => UuidService::generate(),
            'invoice_id' => $ownInvoice->id,
            'batch_id' => $batch->id,
            'debt_obligation_id' => $debt->id,
            'debt_obligation_number_snapshot' => $debt->obligation_number,
            'obligation_type_snapshot' => 'RETURN_FINE',
            'return_reason_snapshot' => 'Customer refused',
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'currency_id' => $currency->id,
            'currency_code_snapshot' => 'LKR',
            'allocated_amount' => '25.00',
            'debt_outstanding_before' => '25.00',
            'debt_outstanding_after' => '0.00',
            'allocation_sequence' => 1,
        ]);

        $service = app(ResellerSettlementInvoiceReadService::class);
        $detail = $service->loadOwnedInvoiceDetail($owner, $ownInvoice);
        $row = $detail['orderRows']->first();

        $this->assertSame($order->order_number, $row['order_number']);
        $this->assertNull($row['waybill']);
        $this->assertSame('2026-10-10', $row['business_date']);
        $this->assertSame('DELIVERED', $row['order_status']);
        $this->assertSame('Delivered', $row['order_status_label']);
        $this->assertSame('1000.00', $row['order_total']);
        $this->assertSame('280.00', $row['eligible_earning']);
        $this->assertSame('280.00', $row['supplier_cost']);
        $this->assertSame('350.00', $row['courier_charge']);
        $this->assertSame('40.00', $row['service_charge']);
        $this->assertSame('50.00', $row['company_commission']);
        $this->assertSame('25.00', $row['deduction_total']);
        $this->assertSame('RETURN_FINE', $row['deductions'][0]['type']);
        $this->assertArrayNotHasKey('net_payable', $row);
        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $detail['orderRows']);

        $html = $this->actingAs($owner)
            ->get(route('settlements.invoices.show', $ownInvoice))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Order-wise breakdown', $html);
        $this->assertStringContainsString($order->order_number, $html);
        $this->assertStringContainsString('badge-status is-delivered', $html);
        $this->assertStringContainsString('Delivered', $html);
        $this->assertStringContainsString('1,000.00', $html);
        $this->assertStringContainsString('280.00', $html);
        $this->assertStringContainsString('350.00', $html);
        $this->assertStringContainsString('RETURN_FINE', $html);
        $this->assertStringNotContainsString('Earning lines', $html);
        $this->assertStringNotContainsString('Payment history', $html);
        $this->assertStringNotContainsString($otherInvoice->invoice_number, $html);
        $this->assertStringNotContainsString('999.00', $html);

        try {
            $service->loadOwnedInvoiceDetail($owner, $otherInvoice);
            $this->fail('Another reseller invoice must stay hidden.');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        }

        $this->actingAs($owner)
            ->get(route('settlements.invoices.show', $otherInvoice))
            ->assertNotFound();
    }

    public function test_order_breakdown_includes_minus_only_orders_and_paginates(): void
    {
        $currency = Currency::query()->where('iso_code', 'LKR')->firstOrFail();
        $market = Market::query()->where('code', 'lk')->firstOrFail();
        $owner = $this->makeResellerWithPermission(['dashboard.view']);

        $batch = ResellerSettlementBatch::query()->create([
            'uuid' => UuidService::generate(),
            'batch_number' => 'RSB-MIN-'.Str::upper(Str::random(5)),
            'market_id' => $market->id,
            'market_code_snapshot' => 'LK',
            'timezone_snapshot' => 'Asia/Colombo',
            'settlement_date' => '2026-10-18',
            'kind' => ResellerSettlementBatch::KIND_DAILY,
            'sequence' => 92,
            'status' => ResellerSettlementBatch::STATUS_LOCKED,
            'generation_source' => ResellerSettlementBatch::GENERATION_SOURCE_MANUAL,
            'generated_by' => $owner->id,
            'generated_at' => now(),
            'locked_by' => $owner->id,
            'locked_at' => now(),
        ]);

        $invoice = ResellerSettlementInvoice::query()->create([
            'uuid' => UuidService::generate(),
            'invoice_number' => 'RSI-MIN-'.Str::upper(Str::random(5)),
            'batch_id' => $batch->id,
            'reseller_id' => $owner->id,
            'reseller_company_id' => $owner->company_id,
            'reseller_name_snapshot' => 'Owner',
            'currency_id' => $currency->id,
            'currency_code_snapshot' => 'LKR',
            'gross_earnings_total' => '0.00',
            'debt_deduction_total' => '26.00',
            'adjustment_total' => '0.00',
            'net_payable' => '0.00',
            'amount_paid' => '0.00',
            'outstanding_balance' => '0.00',
            'payment_status' => ResellerSettlementInvoice::PAYMENT_STATUS_UNPAID,
            'instruction_status' => ResellerSettlementInvoice::INSTRUCTION_STATUS_ACTIVE,
            'issued_at' => now(),
        ]);

        $customer = Customer::query()->create([
            'uuid' => UuidService::generate(),
            'display_name' => 'Minus Customer',
            'primary_country_id' => $market->country_id,
            'is_banned' => false,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        $minusOrderNumbers = [];
        for ($i = 1; $i <= 26; $i++) {
            $order = Order::query()->create([
                'uuid' => UuidService::generate(),
                'order_number' => 'ORD-MIN-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'source' => OrderSource::MANUAL,
                'status' => OrderStatus::RETURNED,
                'market_id' => $market->id,
                'currency_id' => $currency->id,
                'market_code_snapshot' => 'lk',
                'currency_code_snapshot' => 'LKR',
                'reseller_id' => $owner->id,
                'reseller_company_id' => $owner->company_id,
                'supplier_id' => $owner->id,
                'customer_id' => $customer->id,
                'customer_name_snapshot' => 'Minus Customer',
                'primary_phone_snapshot' => '070'.random_int(1000000, 9999999),
                'primary_phone_country_id' => $market->country_id,
                'items_subtotal' => '100.00',
                'discount_amount' => '0.00',
                'courier_fee_amount' => '0.00',
                'customer_payable_amount' => '100.00',
                'total_weight' => '0.500',
                'created_by' => $owner->id,
                'updated_by' => $owner->id,
            ]);
            $minusOrderNumbers[] = $order->order_number;

            $debt = ResellerDebtObligation::query()->create([
                'uuid' => UuidService::generate(),
                'obligation_number' => 'RDO-MIN-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                'reseller_id' => $owner->id,
                'reseller_company_id' => $owner->company_id,
                'order_id' => $order->id,
                'market_id' => $market->id,
                'currency_id' => $currency->id,
                'currency_code_snapshot' => 'LKR',
                'obligation_type' => ResellerDebtObligationType::RETURN_FINE,
                'return_reason' => ResellerReturnReason::DUPLICATE,
                'original_amount' => '1.00',
                'outstanding_amount' => '1.00',
                'effective_date' => '2026-10-17',
                'source_type' => ResellerDebtObligation::SOURCE_SUPPLIER_RETURN_SCAN,
                'source_idempotency_key' => 'return_fine:order:'.$order->id.':'.Str::uuid(),
                'recorded_by' => $owner->id,
            ]);

            ResellerSettlementDebtAllocation::query()->create([
                'uuid' => UuidService::generate(),
                'invoice_id' => $invoice->id,
                'batch_id' => $batch->id,
                'debt_obligation_id' => $debt->id,
                'debt_obligation_number_snapshot' => $debt->obligation_number,
                'obligation_type_snapshot' => 'RETURN_FINE',
                'return_reason_snapshot' => 'Customer refused',
                'order_id' => $order->id,
                'order_number_snapshot' => $order->order_number,
                'currency_id' => $currency->id,
                'currency_code_snapshot' => 'LKR',
                'allocated_amount' => '1.00',
                'debt_outstanding_before' => '1.00',
                'debt_outstanding_after' => '0.00',
                'allocation_sequence' => $i,
            ]);
        }

        $service = app(ResellerSettlementInvoiceReadService::class);
        $detail = $service->loadOwnedInvoiceDetail($owner, $invoice->fresh());
        $this->assertSame(26, $detail['orderRows']->total());
        $this->assertSame(25, $detail['orderRows']->count());
        $this->assertNull($detail['orderRows']->first()['order_total']);
        $this->assertNull($detail['orderRows']->first()['eligible_earning']);
        $this->assertSame('RETURNED', $detail['orderRows']->first()['order_status']);
        $this->assertSame(26.00, (float) $invoice->fresh()->debt_deduction_total);

        $first = $this->actingAs($owner)
            ->get(route('settlements.invoices.show', $invoice))
            ->assertOk()
            ->getContent();
        $second = $this->actingAs($owner)
            ->get(route('settlements.invoices.show', ['invoice' => $invoice, 'page' => 2]))
            ->assertOk()
            ->getContent();

        $firstBreakdown = $this->orderBreakdownSection($first);
        $secondBreakdown = $this->orderBreakdownSection($second);

        $this->assertStringContainsString('Showing 1 to 25', $firstBreakdown);
        $this->assertStringContainsString('of 26 entries', $firstBreakdown);
        $this->assertStringContainsString('Showing 26 to 26', $secondBreakdown);
        $this->assertStringContainsString($minusOrderNumbers[0], $firstBreakdown);
        $this->assertStringContainsString($minusOrderNumbers[24], $firstBreakdown);
        $this->assertStringNotContainsString($minusOrderNumbers[25], $firstBreakdown);
        $this->assertStringContainsString($minusOrderNumbers[25], $secondBreakdown);
        $this->assertStringContainsString('badge-status is-returned', $firstBreakdown);
        $this->assertStringContainsString('26.00', $first);
        $this->assertStringNotContainsString('Earning lines', $first);
        $this->assertStringNotContainsString('Payment history', $first);
    }

    private function orderBreakdownSection(string $html): string
    {
        $start = strpos($html, 'id="resellerSettlementOrderBreakdown"');
        $this->assertNotFalse($start);

        $end = strpos($html, 'Debt deductions', $start);
        $this->assertNotFalse($end);

        return substr($html, $start, $end - $start);
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
            'name' => 'Breakdown Co '.Str::lower(Str::random(4)),
            'email' => 'breakdown-'.Str::uuid().'@feeder.local',
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
                'slug' => 'breakdown-owner-'.Str::lower(Str::random(4)),
            ],
            [
                'uuid' => UuidService::generate(),
                'company_id' => null,
                'name' => 'Breakdown Owner Test',
                'description' => 'Breakdown Owner Test',
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
