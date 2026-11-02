<?php

namespace Tests\Feature\Finance;

use App\Services\Settlement\ResellerSettlementInvoiceReadService;
use Feeder\Core\Enums\UserType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Feeder\Core\Models\Currency;
use Feeder\Core\Models\Market;
use Feeder\Core\Models\ResellerSettlementBatch;
use Feeder\Core\Models\ResellerSettlementInvoice;
use Feeder\Core\Models\User;
use Feeder\Core\Services\PortalFinancialOverviewMarkets;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Reseller financial overview reads, isolated on in-memory SQLite.
 *
 * This test never opens the shared dropshipping database.
 */
class ResellerFinancialOverviewReadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedDatabase();
        $this->createSchema();
    }

    public function test_market_totals_use_stored_invoice_amounts_and_ignore_non_cash_rows(): void
    {
        $this->seedMarkets(true, true);
        $companyId = $this->insertCompany();
        $owner = $this->makeReseller($companyId);
        $colleague = $this->makeReseller($companyId);
        $stranger = $this->makeReseller($this->insertCompany());

        $lk = $this->insertInvoice($owner, 'LK-LIVE', 'LK', '2026-10-10', [
            'gross_earnings_total' => '100.00',
            'debt_deduction_total' => '3.00',
            'adjustment_total' => '50.00',
            'transfer_fee_amount' => '2.00',
            'transfer_fee_currency_code_snapshot' => 'LKR',
            'transfer_fee_status' => ResellerSettlementInvoice::TRANSFER_FEE_STATUS_APPLIED,
            'previous_invoice_offset_total' => '80.00',
            'net_payable' => '100.00',
            'amount_paid' => '20.25',
            'offset_applied_total' => '5.00',
            'outstanding_balance' => '79.75',
            'payment_status' => ResellerSettlementInvoice::PAYMENT_STATUS_PARTIALLY_PAID,
        ]);
        $my = $this->insertInvoice($owner, 'MY-LIVE', 'MY', '2026-10-11', [
            'gross_earnings_total' => '30.00',
            'debt_deduction_total' => '4.00',
            'adjustment_total' => '0.00',
            'transfer_fee_amount' => '9.00',
            'transfer_fee_currency_code_snapshot' => 'MYR',
            'transfer_fee_status' => ResellerSettlementInvoice::TRANSFER_FEE_STATUS_NOT_CONFIGURED,
            'previous_invoice_offset_total' => '0.00',
            'net_payable' => '30.00',
            'amount_paid' => '1.00',
            'offset_applied_total' => '0.00',
            'outstanding_balance' => '16.00',
            'payment_status' => ResellerSettlementInvoice::PAYMENT_STATUS_PARTIALLY_PAID,
        ]);

        $this->insertInvoice($colleague, 'COLLEAGUE', 'LK', '2026-10-10', $this->money('8000.00'));
        $this->insertInvoice($stranger, 'STRANGER', 'LK', '2026-10-10', $this->money('7000.00'));
        $this->insertInvoice($owner, 'VOID-INVOICE', 'LK', '2026-10-10', $this->money('9000.00'), [
            'voided_at' => now(),
        ]);
        $this->insertInvoice($owner, 'VOID-INSTRUCTION', 'LK', '2026-10-10', $this->money('6000.00'), [
            'instruction_status' => ResellerSettlementInvoice::INSTRUCTION_STATUS_VOIDED,
        ]);
        $voidedBatch = $this->insertInvoice($owner, 'VOID-BATCH', 'LK', '2026-10-12', $this->money('5000.00'));
        $voidedBatch->batch->forceFill(['voided_at' => now()])->save();

        DB::table('reseller_payments')->insert([
            'invoice_id' => $lk->id,
            'reseller_id' => $owner->id,
            'amount' => '999.00',
            'payment_reference' => 'CASH-LEDGER',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('reseller_settlement_invoice_offsets')->insert([
            'source_invoice_id' => $lk->id,
            'target_invoice_id' => $my->id,
            'reseller_id' => $owner->id,
            'allocated_amount' => '4321.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summaries = app(ResellerSettlementInvoiceReadService::class)->financialSummaries(
            $owner,
            app(PortalFinancialOverviewMarkets::class)->active(),
        );

        $this->assertSame('100.00', $summaries['lk']['total_earnings']);
        $this->assertSame('5.00', $summaries['lk']['total_deductions']);
        $this->assertSame('20.25', $summaries['lk']['total_paid']);
        $this->assertSame('79.75', $summaries['lk']['outstanding']);
        $this->assertSame('30.00', $summaries['my']['total_earnings']);
        $this->assertSame('4.00', $summaries['my']['total_deductions']);
        $this->assertSame('1.00', $summaries['my']['total_paid']);
        $this->assertSame('16.00', $summaries['my']['outstanding']);

        $colleagueSummaries = app(ResellerSettlementInvoiceReadService::class)->financialSummaries(
            $colleague,
            app(PortalFinancialOverviewMarkets::class)->active(),
        );
        $this->assertSame('8000.00', $colleagueSummaries['lk']['total_earnings']);
        $this->assertSame('0.00', $colleagueSummaries['my']['total_earnings']);

        $html = $this->actingAs($owner)->get(route('finance.overview', [
            'reseller_id' => $stranger->id,
        ]))->assertOk()->getContent();
        $sriLanka = $this->section($html, 'financial-overview-market-lk');
        $malaysia = $this->section($html, 'financial-overview-market-my');

        $this->assertStringContainsString('100.00', $sriLanka);
        $this->assertStringContainsString('5.00', $sriLanka);
        $this->assertStringContainsString('20.25', $sriLanka);
        $this->assertStringContainsString('79.75', $sriLanka);
        $this->assertStringNotContainsString('MYR', $sriLanka);
        $this->assertStringContainsString('30.00', $malaysia);
        $this->assertStringContainsString('4.00', $malaysia);
        $this->assertStringNotContainsString('9.00', $malaysia);
        $this->assertStringNotContainsString('LKR', $malaysia);
        $this->assertStringContainsString('transfer_fee_not_configured', $html);
        $this->assertStringContainsString('applied', $html);
        $this->assertStringContainsString(route('settlements.invoices.show', $lk), $html);
        $this->assertStringNotContainsString(route('settlements.invoices.show', $voidedBatch), $html);
        $this->assertStringNotContainsString('VOID-BATCH', $html);
        $this->assertStringNotContainsString('VOID-INSTRUCTION', $html);
        $this->assertStringNotContainsString('VOID-INVOICE', $html);
        $this->assertStringNotContainsString('999.00', $html);
        $this->assertStringNotContainsString('4321.00', $html);
        $this->assertStringNotContainsString('8000.00', $html);
        $this->assertStringNotContainsString('7000.00', $html);
        $this->assertStringNotContainsString('9000.00', $html);
        $this->assertStringNotContainsString('6000.00', $html);
        $this->assertStringNotContainsString('74.75', $html);
        $this->assertStringNotContainsString('Pending integration', $html);
    }

    public function test_ineligible_invoices_are_absent_from_the_overview_table_and_cards(): void
    {
        $this->seedMarkets(true, false);
        $owner = $this->makeReseller($this->insertCompany());
        $stranger = $this->makeReseller($this->insertCompany());
        $this->insertInvoice($owner, 'LIVE', 'LK', '2026-10-04', $this->money('12.00'));
        $voidedInvoice = $this->insertInvoice($owner, 'VOID-INVOICE', 'LK', '2026-10-04', $this->money('9000.00'), [
            'voided_at' => now(),
        ]);
        $voidedInstruction = $this->insertInvoice($owner, 'VOID-INSTRUCTION', 'LK', '2026-10-04', $this->money('6000.00'), [
            'instruction_status' => ResellerSettlementInvoice::INSTRUCTION_STATUS_VOIDED,
        ]);
        $voidedBatch = $this->insertInvoice($owner, 'VOID-BATCH', 'LK', '2026-10-04', $this->money('5000.00'));
        $voidedBatch->batch->forceFill(['voided_at' => now()])->save();
        $this->insertInvoice($stranger, 'STRANGER', 'LK', '2026-10-04', $this->money('7000.00'));

        $service = app(ResellerSettlementInvoiceReadService::class);
        $markets = app(PortalFinancialOverviewMarkets::class)->active();
        $summary = $service->financialSummaries($owner, $markets)['lk'];
        $overviewRows = $service->paginateForFinancialOverview($owner, 15)->getCollection();
        $invoiceListRows = $service->paginateForReseller($owner, 15)->getCollection();

        $this->assertSame('12.00', $summary['total_earnings']);
        $this->assertSame(['LIVE'], $overviewRows->map(fn (ResellerSettlementInvoice $invoice): string => (string) $invoice->batch->batch_number)->all());
        $this->assertEqualsCanonicalizing(
            ['LIVE', 'VOID-INSTRUCTION', 'VOID-BATCH'],
            $invoiceListRows->map(fn (ResellerSettlementInvoice $invoice): string => (string) $invoice->batch->batch_number)->all(),
        );

        $service->assertOwned($owner, $voidedInstruction);
        $service->assertOwned($owner, $voidedBatch);

        try {
            $service->assertOwned($owner, $voidedInvoice);
            $this->fail('A voided invoice remains hidden from detail.');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        }

        try {
            $service->assertOwned($stranger, $voidedBatch);
            $this->fail('Another reseller cannot open this invoice.');
        } catch (NotFoundHttpException) {
            $this->assertTrue(true);
        }

        $html = $this->actingAs($owner)->get(route('finance.overview'))->assertOk()->getContent();
        $this->assertStringContainsString('LIVE', $html);
        $this->assertStringNotContainsString('VOID-INVOICE', $html);
        $this->assertStringNotContainsString('VOID-INSTRUCTION', $html);
        $this->assertStringNotContainsString('VOID-BATCH', $html);
        $this->assertStringNotContainsString('5,000.00', $html);
        $this->assertStringNotContainsString('6,000.00', $html);
        $this->assertStringNotContainsString('9,000.00', $html);
    }

    public function test_applied_transfer_fee_in_another_currency_is_excluded_with_a_warning(): void
    {
        $this->seedMarkets(true, true);
        $reseller = $this->makeReseller($this->insertCompany());
        $this->insertInvoice($reseller, 'FEE-MISMATCH', 'LK', '2026-10-08', [
            'gross_earnings_total' => '100.00',
            'debt_deduction_total' => '3.00',
            'adjustment_total' => '0.00',
            'transfer_fee_amount' => '2.00',
            'transfer_fee_currency_code_snapshot' => 'USD',
            'transfer_fee_status' => ResellerSettlementInvoice::TRANSFER_FEE_STATUS_APPLIED,
            'previous_invoice_offset_total' => '0.00',
            'net_payable' => '95.00',
            'amount_paid' => '0.00',
            'offset_applied_total' => '0.00',
            'outstanding_balance' => '95.00',
            'payment_status' => ResellerSettlementInvoice::PAYMENT_STATUS_UNPAID,
        ]);

        $summary = app(ResellerSettlementInvoiceReadService::class)->financialSummaries(
            $reseller,
            app(PortalFinancialOverviewMarkets::class)->active(),
        )['lk'];

        $this->assertSame('100.00', $summary['total_earnings']);
        $this->assertSame('3.00', $summary['total_deductions']);
        $this->assertSame('95.00', $summary['outstanding']);
        $this->assertSame(
            ['An applied transfer fee is stored in USD, not LKR. That fee is not included in these totals.'],
            $summary['notices'],
        );
        $this->assertSame([], app(ResellerSettlementInvoiceReadService::class)->financialSummaries(
            $reseller,
            app(PortalFinancialOverviewMarkets::class)->active(),
        )['my']['notices']);

        $html = $this->actingAs($reseller)->get(route('finance.overview'))->assertOk()->getContent();
        $sriLanka = $this->section($html, 'financial-overview-market-lk');

        $this->assertStringContainsString('An applied transfer fee is stored in USD, not LKR. That fee is not included in these totals.', $sriLanka);
        $this->assertStringContainsString('3.00', $sriLanka);
        $this->assertStringNotContainsString('2.00', $sriLanka);
        $this->assertStringContainsString('USD', $html);
        $this->assertStringContainsString('Not included in the LKR deduction total. No exchange rate is applied.', $html);
        $this->assertStringNotContainsString('Pending integration', $html);
    }

    public function test_inactive_market_cards_stay_hidden(): void
    {
        $this->seedMarkets(true, false);
        $reseller = $this->makeReseller($this->insertCompany());
        $this->insertInvoice($reseller, 'LK-ONLY', 'LK', '2026-10-03', $this->money('12.00'));
        $this->insertInvoice($reseller, 'MY-HIDDEN', 'MY', '2026-10-03', $this->money('44.00'));

        $summaries = app(ResellerSettlementInvoiceReadService::class)->financialSummaries(
            $reseller,
            app(PortalFinancialOverviewMarkets::class)->active(),
        );

        $this->assertSame('12.00', $summaries['lk']['total_earnings']);
        $this->assertArrayNotHasKey('my', $summaries);

        $html = $this->actingAs($reseller)->get(route('finance.overview'))->assertOk()->getContent();

        $this->assertStringContainsString('id="financial-overview-market-lk"', $html);
        $this->assertStringNotContainsString('id="financial-overview-market-my"', $html);
        $this->assertSame(4, substr_count($html, 'h-100 finance-today-card'));
    }

    public function test_date_filter_limits_the_table_and_preserves_detail_links(): void
    {
        $this->seedMarkets(true, false);
        $reseller = $this->makeReseller($this->insertCompany());
        $kept = $this->insertInvoice($reseller, 'KEEP', 'LK', '2026-10-20', $this->money('15.00'));
        $dropped = $this->insertInvoice($reseller, 'DROP', 'LK', '2026-10-01', $this->money('18.00'));

        $html = $this->actingAs($reseller)->get(route('finance.overview', [
            'date_from' => '2026-10-20',
            'date_to' => '2026-10-20',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('33.00', $html);
        $this->assertStringContainsString('KEEP', $html);
        $this->assertStringContainsString(route('settlements.invoices.show', $kept), $html);
        $this->assertStringNotContainsString('DROP', $html);
        $this->assertStringNotContainsString(route('settlements.invoices.show', $dropped), $html);
        $this->assertStringNotContainsString('18.00', $html);
    }

    public function test_table_pagination_stays_at_fifteen_rows(): void
    {
        $this->seedMarkets(true, false);
        $reseller = $this->makeReseller($this->insertCompany());

        for ($day = 1; $day <= 16; $day++) {
            $this->insertInvoice(
                $reseller,
                'PAGE-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT),
                'LK',
                sprintf('2026-10-%02d', $day),
                $this->money('1.00'),
                ['issued_at' => sprintf('2026-10-%02d 00:00:00', $day)],
            );
        }

        $first = $this->actingAs($reseller)->get(route('finance.overview'))->assertOk()->getContent();

        $this->assertStringContainsString('Showing 1 to 15', $first);
        $this->assertStringContainsString('of 16 entries', $first);
        $this->assertStringContainsString('PAGE-16', $first);
        $this->assertStringNotContainsString('PAGE-01', $first);
    }

    public function test_empty_dataset_renders_zero_cards_and_the_empty_table(): void
    {
        $this->seedMarkets(true, true);
        $reseller = $this->makeReseller($this->insertCompany());

        $html = $this->actingAs($reseller)->get(route('finance.overview'))->assertOk()->getContent();

        $this->assertSame(8, substr_count($html, 'h-100 finance-today-card'));
        $this->assertSame(8, substr_count($html, '0.00'));
        $this->assertStringNotContainsString('Pending integration', $html);
        $this->assertStringContainsString('No settlement invoices found for your account.', $html);
        $this->assertStringNotContainsString('Eligible earnings is the stored', $html);
        $this->assertStringNotContainsString('Historical supplier cost, courier charge', $html);
    }

    public function test_registered_market_cards_only_follow_allowed_markets(): void
    {
        $this->seedMarkets(true, true);
        $lkOnly = $this->makeReseller($this->insertCompany(), ['lk']);
        $myOnly = $this->makeReseller($this->insertCompany(), ['my']);
        $this->insertInvoice($lkOnly, 'LK-REG', 'LK', '2026-10-05', $this->money('12.00'));
        $this->insertInvoice($lkOnly, 'MY-TXN', 'MY', '2026-10-05', $this->money('44.00'));

        $lkHtml = $this->actingAs($lkOnly)->get(route('finance.overview'))->assertOk()->getContent();
        $this->assertStringContainsString('id="financial-overview-market-lk"', $lkHtml);
        $this->assertStringNotContainsString('id="financial-overview-market-my"', $lkHtml);
        $this->assertSame(4, substr_count($lkHtml, 'h-100 finance-today-card'));

        $myHtml = $this->actingAs($myOnly)->get(route('finance.overview'))->assertOk()->getContent();
        $this->assertStringContainsString('id="financial-overview-market-my"', $myHtml);
        $this->assertStringNotContainsString('id="financial-overview-market-lk"', $myHtml);
        $this->assertSame(4, substr_count($myHtml, 'h-100 finance-today-card'));
    }

    public function test_cards_are_omitted_when_neither_market_is_active(): void
    {
        $this->seedMarkets(false, false);
        $reseller = $this->makeReseller($this->insertCompany());

        $html = $this->actingAs($reseller)->get(route('finance.overview'))->assertOk()->getContent();

        $this->assertStringContainsString('No eligible Sri Lanka or Malaysia market is available for your account.', $html);
        $this->assertStringNotContainsString('h-100 finance-today-card', $html);
        $this->assertStringContainsString('No settlement invoices found for your account.', $html);
    }

    private function useIsolatedDatabase(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite extension is required for this test.');
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertNotSame('dropshipping', (string) DB::connection()->getDatabaseName());
        $this->assertSame(':memory:', (string) config('database.connections.sqlite.database'));
    }

    private function createSchema(): void
    {
        Schema::dropAllTables();

        Schema::create('portals', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('portal_id')->nullable();
            $table->string('name')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('user_type')->nullable();
            $table->string('status')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('portal_id')->nullable();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('portal_id')->nullable();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('permission_id');
            $table->timestamps();
        });

        Schema::create('user_permissions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('permission_id');
            $table->boolean('allowed')->default(true);
            $table->timestamps();
        });

        Schema::create('currencies', function (Blueprint $table): void {
            $table->id();
            $table->string('iso_code', 3);
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('markets', function (Blueprint $table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('reseller_market_access', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('market_id');
            $table->unsignedBigInteger('granted_by')->nullable();
            $table->timestamps();
        });

        Schema::create('reseller_settlement_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 10)->nullable();
            $table->string('batch_number')->nullable();
            $table->unsignedBigInteger('market_id')->nullable();
            $table->string('market_code_snapshot')->nullable();
            $table->string('timezone_snapshot')->nullable();
            $table->date('settlement_date')->nullable();
            $table->string('kind')->nullable();
            $table->unsignedSmallInteger('sequence')->nullable();
            $table->string('status')->nullable();
            $table->string('generation_source')->nullable();
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reseller_settlement_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 10)->nullable();
            $table->string('invoice_number')->nullable();
            $table->unsignedBigInteger('batch_id')->nullable();
            $table->unsignedBigInteger('reseller_id')->nullable();
            $table->unsignedBigInteger('reseller_company_id')->nullable();
            $table->string('reseller_name_snapshot')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('currency_code_snapshot', 3)->nullable();
            $table->decimal('gross_earnings_total', 15, 2)->default(0);
            $table->decimal('debt_deduction_total', 15, 2)->default(0);
            $table->decimal('adjustment_total', 15, 2)->default(0);
            $table->decimal('transfer_fee_amount', 15, 2)->default(0);
            $table->string('transfer_fee_currency_code_snapshot', 3)->nullable();
            $table->string('transfer_fee_status')->nullable();
            $table->decimal('previous_invoice_offset_total', 15, 2)->default(0);
            $table->decimal('net_payable', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('offset_applied_total', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2)->default(0);
            $table->string('payment_status')->nullable();
            $table->string('instruction_status')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reseller_payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('reseller_id')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('payment_reference')->nullable();
            $table->timestamps();
        });

        Schema::create('reseller_settlement_invoice_offsets', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_invoice_id')->nullable();
            $table->unsignedBigInteger('target_invoice_id')->nullable();
            $table->unsignedBigInteger('reseller_id')->nullable();
            $table->decimal('allocated_amount', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('reseller_settlement_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('order_number_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('reseller_settlement_debt_allocations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('order_number_snapshot')->nullable();
            $table->string('obligation_type_snapshot')->nullable();
            $table->decimal('allocated_amount', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    private function seedMarkets(bool $sriLankaActive, bool $malaysiaActive): void
    {
        $lkr = Currency::query()->create(['iso_code' => 'LKR', 'name' => 'Sri Lankan Rupee', 'is_active' => true]);
        $myr = Currency::query()->create(['iso_code' => 'MYR', 'name' => 'Malaysian Ringgit', 'is_active' => true]);

        Market::query()->create([
            'code' => 'lk',
            'name' => 'Sri Lanka',
            'currency_id' => $lkr->id,
            'is_active' => $sriLankaActive,
        ]);
        Market::query()->create([
            'code' => 'my',
            'name' => 'Malaysia',
            'currency_id' => $myr->id,
            'is_active' => $malaysiaActive,
        ]);
    }

    private function insertCompany(): int
    {
        $portalId = DB::table('portals')->where('code', 'RESELLER')->value('id');

        if ($portalId === null) {
            $portalId = DB::table('portals')->insertGetId([
                'code' => 'RESELLER',
                'name' => 'Reseller',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return DB::table('companies')->insertGetId([
            'portal_id' => $portalId,
            'name' => 'Reseller Co '.DB::table('companies')->count(),
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $marketCodes
     */
    private function makeReseller(int $companyId, array $marketCodes = ['lk', 'my']): User
    {
        $portalId = DB::table('portals')->where('code', 'RESELLER')->value('id');

        if ($portalId === null) {
            $portalId = DB::table('portals')->insertGetId([
                'code' => 'RESELLER',
                'name' => 'Reseller',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('companies')->where('id', $companyId)->update(['portal_id' => $portalId]);

        $roleId = DB::table('roles')->insertGetId([
            'portal_id' => $portalId,
            'name' => 'Reseller',
            'slug' => 'reseller-'.$companyId.'-'.DB::table('roles')->count(),
            'is_system' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->where('slug', 'dashboard.view')->value('id');

        if ($permissionId === null) {
            $permissionId = DB::table('permissions')->insertGetId([
                'portal_id' => $portalId,
                'name' => 'Dashboard',
                'slug' => 'dashboard.view',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'company_id' => $companyId,
            'role_id' => $roleId,
            'email' => 'reseller-'.$roleId.'@feeder.test',
            'password' => Hash::make('password'),
            'user_type' => UserType::OWNER->value,
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->grantMarkets($companyId, $marketCodes);

        return User::query()->findOrFail($userId);
    }

    /**
     * @param  list<string>  $marketCodes
     */
    private function grantMarkets(int $companyId, array $marketCodes): void
    {
        foreach ($marketCodes as $code) {
            $marketId = DB::table('markets')->where('code', $code)->value('id');

            if ($marketId === null) {
                continue;
            }

            $exists = DB::table('reseller_market_access')
                ->where('company_id', $companyId)
                ->where('market_id', $marketId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('reseller_market_access')->insert([
                'company_id' => $companyId,
                'market_id' => $marketId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $amounts
     * @param  array<string, mixed>  $overrides
     */
    private function insertInvoice(
        User $reseller,
        string $batchNumber,
        string $marketCode,
        string $settlementDate,
        array $amounts,
        array $overrides = [],
    ): ResellerSettlementInvoice {
        $batch = ResellerSettlementBatch::query()->create([
            'batch_number' => $batchNumber,
            'market_id' => 1,
            'market_code_snapshot' => $marketCode,
            'timezone_snapshot' => $marketCode === 'MY' ? 'Asia/Kuala_Lumpur' : 'Asia/Colombo',
            'settlement_date' => $settlementDate,
            'kind' => ResellerSettlementBatch::KIND_DAILY,
            'sequence' => 1,
            'status' => ResellerSettlementBatch::STATUS_LOCKED,
            'generation_source' => ResellerSettlementBatch::GENERATION_SOURCE_MANUAL,
            'generated_by' => $reseller->id,
            'generated_at' => now(),
        ]);

        return ResellerSettlementInvoice::query()->create(array_merge([
            'invoice_number' => $batchNumber,
            'batch_id' => $batch->id,
            'reseller_id' => $reseller->id,
            'reseller_company_id' => $reseller->company_id,
            'reseller_name_snapshot' => 'Reseller',
            'currency_id' => 1,
            'currency_code_snapshot' => $marketCode === 'MY' ? 'MYR' : 'LKR',
            'instruction_status' => ResellerSettlementInvoice::INSTRUCTION_STATUS_ACTIVE,
            'issued_at' => $settlementDate.' 12:00:00',
        ], $amounts, $overrides));
    }

    /**
     * @return array<string, string>
     */
    private function money(string $gross): array
    {
        return [
            'gross_earnings_total' => $gross,
            'debt_deduction_total' => '0.00',
            'adjustment_total' => '0.00',
            'transfer_fee_amount' => '0.00',
            'transfer_fee_currency_code_snapshot' => 'LKR',
            'transfer_fee_status' => ResellerSettlementInvoice::TRANSFER_FEE_STATUS_NOT_CONFIGURED,
            'previous_invoice_offset_total' => '0.00',
            'net_payable' => $gross,
            'amount_paid' => '0.00',
            'offset_applied_total' => '0.00',
            'outstanding_balance' => $gross,
            'payment_status' => ResellerSettlementInvoice::PAYMENT_STATUS_UNPAID,
        ];
    }

    private function section(string $html, string $id): string
    {
        $start = strpos($html, 'id="'.$id.'"');
        $this->assertNotFalse($start);
        $candidates = array_filter(
            [
                strpos($html, '<section', $start + 1),
                strpos($html, 'FinancialBatchReport', $start + 1),
            ],
            static fn (int|false $value): bool => $value !== false,
        );
        $next = $candidates === [] ? false : min($candidates);

        return $next === false ? substr($html, $start) : substr($html, $start, $next - $start);
    }
}
