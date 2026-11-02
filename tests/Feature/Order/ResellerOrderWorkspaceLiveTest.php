<?php

namespace Tests\Feature\Order;

use App\Services\Order\ResellerOrderWorkspaceService;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\OrderSource;
use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\ShipmentStatus;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Courier;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\OrderItem;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\ResellerSettlementInvoice;
use Feeder\Core\Models\ResellerSettlementInvoiceLine;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\Shipment;
use Feeder\Core\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;

/**
 * Live Ongoing, Completed, and Archived workspaces.
 *
 * Prefers SQLite memory. This environment has no SQLite driver, so the test
 * uses the separate database dropshipping_reseller_workspace_test.
 * It never connects to the shared dropshipping database.
 */
class ResellerOrderWorkspaceLiveTest extends TestCase
{
    private Portal $portal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useIsolatedDatabase();
        $this->createSchema();
        config(['cache.default' => 'array']);

        $this->portal = Portal::query()->create([
            'uuid' => (string) Str::uuid(),
            'code' => PortalCode::RESELLER->value,
            'name' => 'Reseller Portal',
            'subdomain' => 'reseller-test',
            'description' => 'Reseller Portal',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_workspaces_include_only_their_real_orders_for_the_authenticated_reseller(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-22 12:00:00'));

        $reseller = $this->makeReseller(['orders.view']);
        $other = $this->makeReseller(['orders.view']);
        $courier = $this->makeCourier('City Courier');
        $otherCourier = $this->makeCourier('Other Courier');

        $print = $this->makeOrder($reseller, OrderStatus::PRINT, 'ORD-PRINT', 'Print Customer', '0771000001');
        $this->makeShipment($print, $courier, 'WB-PRINT');
        $printed = $this->makeOrder($reseller, OrderStatus::PRINTED, 'ORD-PRINTED', 'Printed Customer', '0771000002');
        $this->makeShipment($printed, $courier, 'WB-PRINTED');
        $packing = $this->makeOrder($reseller, OrderStatus::PACKING, 'ORD-PACKING', 'Packing Customer', '0771000003');
        $dispatched = $this->makeOrder($reseller, OrderStatus::DISPATCHED, 'ORD-DISPATCHED', 'Dispatch Customer', '0771000004');
        $this->makeShipment($dispatched, $courier, null);

        $this->makeOrder($reseller, OrderStatus::PENDING, 'ORD-PENDING', 'Pending Customer', '0771000005');
        $this->makeOrder($reseller, OrderStatus::CONFIRMED, 'ORD-CONFIRMED', 'Confirmed Customer', '0771000006');
        $this->makeOrder($reseller, OrderStatus::CANCELLED, 'ORD-CANCELLED', 'Cancelled Customer', '0771000007');

        $recentDelivered = $this->makeOrder($reseller, OrderStatus::DELIVERED, 'ORD-DEL-RECENT', 'Recent Delivered', '0772000001', [
            'delivered_at' => now()->subDays(3),
            'customer_payable_amount' => 4500,
        ]);
        $this->makeShipment($recentDelivered, $courier, 'WB-RECENT');
        $this->makeSettlementLine($recentDelivered, 'INV-RECENT', ResellerSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID);

        $boundaryDelivered = $this->makeOrder($reseller, OrderStatus::DELIVERED, 'ORD-DEL-BOUNDARY', 'Boundary Delivered', '0772000002', [
            'delivered_at' => now()->subDays(ResellerOrderWorkspaceService::COMPLETED_WINDOW_DAYS),
        ]);

        $nullDelivered = $this->makeOrder($reseller, OrderStatus::DELIVERED, 'ORD-DEL-NULL', 'Undated Delivered', '0772000003', [
            'delivered_at' => null,
        ]);

        $recentReturned = $this->makeOrder($reseller, OrderStatus::RETURNED, 'ORD-RET-RECENT', 'Recent Returned', '0772000004', [
            'returned_at' => now()->subDay(),
        ]);

        $oldDelivered = $this->makeOrder($reseller, OrderStatus::DELIVERED, 'ORD-DEL-OLD', 'Old Delivered', '0773000001', [
            'delivered_at' => now()->subDays(ResellerOrderWorkspaceService::COMPLETED_WINDOW_DAYS)->subSecond(),
        ]);
        $this->makeShipment($oldDelivered, $courier, 'WB-OLD');

        $oldReturned = $this->makeOrder($reseller, OrderStatus::RETURNED, 'ORD-RET-OLD', 'Old Returned', '0773000002', [
            'returned_at' => now()->subDays(10),
        ]);
        $this->makeSettlementLine($oldReturned, 'INV-OLD', ResellerSettlementInvoiceLine::LINE_PAYMENT_STATUS_UNPAID);

        $voided = $this->makeOrder($reseller, OrderStatus::DELIVERED, 'ORD-DEL-VOID', 'Voided Settlement', '0772000005', [
            'delivered_at' => now()->subDays(2),
        ]);
        $this->makeSettlementLine(
            $voided,
            'INV-VOID',
            ResellerSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID,
            ResellerSettlementInvoice::INSTRUCTION_STATUS_VOIDED,
        );

        $foreign = $this->makeOrder($other, OrderStatus::DISPATCHED, 'ORD-FOREIGN-ONGOING', 'Foreign Ongoing', '0780000001');
        $this->makeShipment($foreign, $otherCourier, 'WB-FOREIGN');
        $foreignDone = $this->makeOrder($other, OrderStatus::DELIVERED, 'ORD-FOREIGN-DONE', 'Foreign Delivered', '0780000002', [
            'delivered_at' => now()->subDay(),
        ]);
        $foreignOld = $this->makeOrder($other, OrderStatus::RETURNED, 'ORD-FOREIGN-OLD', 'Foreign Archived', '0780000003', [
            'returned_at' => now()->subDays(20),
        ]);

        $ongoing = $this->actingAs($reseller)
            ->get(route('orders.index', [
                'reseller_company_id' => $other->company_id,
                'status' => OrderStatus::DELIVERED->value,
                'order' => $foreign->uuid,
            ]))
            ->assertOk()
            ->assertSee('id="resellerOngoingOrdersPage"', false)
            ->assertSee('>'.$courier->name.'<', false)
            ->assertSee('Serum / 30ml', false)
            ->assertDontSee($otherCourier->name)
            ->assertDontSee('data-mock-orders', false);

        $ongoingOrders = $this->ordersFrom($ongoing->getContent());
        $ongoingNumbers = array_column($ongoingOrders, 'order_number');

        $this->assertEqualsCanonicalizing([
            'ORD-PRINT',
            'ORD-PRINTED',
            'ORD-PACKING',
            'ORD-DISPATCHED',
        ], $ongoingNumbers);
        $this->assertNotContains('ORD-PENDING', $ongoingNumbers);
        $this->assertNotContains('ORD-CONFIRMED', $ongoingNumbers);
        $this->assertNotContains('ORD-CANCELLED', $ongoingNumbers);
        $this->assertNotContains('ORD-DEL-RECENT', $ongoingNumbers);
        $this->assertNotContains('ORD-FOREIGN-ONGOING', $ongoingNumbers);

        $printRow = $this->row($ongoingOrders, 'ORD-PRINT');
        $this->assertSame('Print Customer', $printRow['customer_name']);
        $this->assertSame('0771000001', $printRow['primary_phone']);
        $this->assertSame('Serum / 30ml', $printRow['product']);
        $this->assertSame(OrderStatus::PRINT->value, $printRow['status']);
        $this->assertSame('City Courier', $printRow['courier']);
        $this->assertSame('WB-PRINT', $printRow['waybill']);
        $this->assertSame('250.00', $printRow['cod_amount']);
        $this->assertSame('LKR', $printRow['currency_code']);
        $this->assertNotEmpty($printRow['updated_at']);
        $this->assertStringContainsString($print->uuid, (string) $printRow['detail_url']);

        $packingRow = $this->row($ongoingOrders, 'ORD-PACKING');
        $this->assertNull($packingRow['courier']);
        $this->assertNull($packingRow['waybill']);

        $dispatchedRow = $this->row($ongoingOrders, 'ORD-DISPATCHED');
        $this->assertSame('City Courier', $dispatchedRow['courier']);
        $this->assertNull($dispatchedRow['waybill']);

        $this->assertSame(1, $this->summaryCount($ongoing->getContent(), 'ongoingSummaryPrint'));
        $this->assertSame(1, $this->summaryCount($ongoing->getContent(), 'ongoingSummaryPrinted'));
        $this->assertSame(1, $this->summaryCount($ongoing->getContent(), 'ongoingSummaryPacking'));
        $this->assertSame(1, $this->summaryCount($ongoing->getContent(), 'ongoingSummaryDispatched'));

        $completed = $this->actingAs($reseller)
            ->get(route('orders.index', ['workspace' => 'archived']))
            ->assertOk()
            ->assertSee('id="resellerCompletedOrdersPage"', false)
            ->assertDontSee('data-mock-orders', false)
            ->assertDontSee('ORD-FOREIGN-DONE');

        $completedOrders = $this->ordersFrom($completed->getContent());
        $completedNumbers = array_column($completedOrders, 'order_number');

        $this->assertEqualsCanonicalizing([
            'ORD-DEL-RECENT',
            'ORD-DEL-BOUNDARY',
            'ORD-DEL-NULL',
            'ORD-RET-RECENT',
            'ORD-DEL-VOID',
        ], $completedNumbers);

        $recentRow = $this->row($completedOrders, 'ORD-DEL-RECENT');
        $this->assertSame(OrderStatus::DELIVERED->value, $recentRow['outcome']);
        $this->assertSame('City Courier', $recentRow['courier']);
        $this->assertSame('WB-RECENT', $recentRow['waybill']);
        $this->assertSame('4,500.00', $recentRow['cod_amount']);
        $this->assertSame(ResellerOrderWorkspaceService::SETTLEMENT_SETTLED, $recentRow['settlement_status']);
        $this->assertSame('INV-RECENT', $recentRow['invoice_number']);
        $this->assertNotEmpty($recentRow['completed_at']);
        $this->assertNotEmpty($recentRow['completed_date']);

        $this->assertSame(
            ResellerOrderWorkspaceService::SETTLEMENT_PENDING,
            $this->row($completedOrders, 'ORD-DEL-BOUNDARY')['settlement_status']
        );
        $this->assertSame('', $this->row($completedOrders, 'ORD-DEL-NULL')['completed_date']);
        $this->assertNull($this->row($completedOrders, 'ORD-RET-RECENT')['waybill']);
        $this->assertSame(
            OrderStatus::RETURNED->value,
            $this->row($completedOrders, 'ORD-RET-RECENT')['outcome']
        );
        $this->assertSame(
            ResellerOrderWorkspaceService::SETTLEMENT_PENDING,
            $this->row($completedOrders, 'ORD-DEL-VOID')['settlement_status']
        );

        $this->assertSame(4, $this->summaryCount($completed->getContent(), 'completedSummaryDelivered'));
        $this->assertSame(1, $this->summaryCount($completed->getContent(), 'completedSummaryReturned'));
        $this->assertSame(4, $this->summaryCount($completed->getContent(), 'completedSummarySettlementPending'));
        $this->assertSame(1, $this->summaryCount($completed->getContent(), 'completedSummarySettled'));

        $archived = $this->actingAs($reseller)
            ->get(route('orders.index', [
                'workspace' => 'archived-orders',
                'reseller_company_id' => $other->company_id,
            ]))
            ->assertOk()
            ->assertSee('id="resellerArchivedOrdersPage"', false)
            ->assertDontSee('data-mock-orders', false)
            ->assertDontSee('ORD-FOREIGN-OLD')
            ->assertDontSee('ORD-DEL-RECENT');

        $archivedOrders = $this->ordersFrom($archived->getContent());
        $this->assertEqualsCanonicalizing(
            ['ORD-DEL-OLD', 'ORD-RET-OLD'],
            array_column($archivedOrders, 'order_number')
        );
        $this->assertSame('WB-OLD', $this->row($archivedOrders, 'ORD-DEL-OLD')['waybill']);
        $this->assertSame(
            ResellerOrderWorkspaceService::SETTLEMENT_PENDING,
            $this->row($archivedOrders, 'ORD-RET-OLD')['settlement_status']
        );
        $this->assertSame('INV-OLD', $this->row($archivedOrders, 'ORD-RET-OLD')['invoice_number']);
        $this->assertSame(1, $this->summaryCount($archived->getContent(), 'archivedSummaryDelivered'));
        $this->assertSame(1, $this->summaryCount($archived->getContent(), 'archivedSummaryReturned'));

        $this->actingAs($other)
            ->get(route('orders.index'))
            ->assertOk()
            ->assertSee('ORD-FOREIGN-ONGOING')
            ->assertDontSee('ORD-PRINT')
            ->assertDontSee('ORD-DISPATCHED');

        unset($foreignDone, $foreignOld);
    }

    public function test_empty_workspaces_render_without_mock_orders(): void
    {
        $reseller = $this->makeReseller(['orders.view']);

        foreach ([
            route('orders.index') => 'No orders are currently in fulfillment.',
            route('orders.index', ['workspace' => 'archived']) => 'No delivered or returned orders yet.',
            route('orders.index', ['workspace' => 'archived-orders']) => 'No archived orders found.',
        ] as $url => $emptyMessage) {
            $response = $this->actingAs($reseller)->get($url)->assertOk();
            $content = $response->getContent();

            $this->assertSame([], $this->ordersFrom($content));
            $response
                ->assertSee($emptyMessage)
                ->assertSee('data-orders=', false)
                ->assertDontSee('data-mock-orders', false)
                ->assertDontSee('Kasun Perera')
                ->assertDontSee('12610200123')
                ->assertDontSee('Mahesh Wickrama')
                ->assertDontSee('Royal Express')
                ->assertDontSee('RX123456');
        }
    }

    public function test_search_filter_controls_and_pagination_stay_available(): void
    {
        $reseller = $this->makeReseller(['orders.view']);
        $courier = $this->makeCourier('Paged Courier');

        for ($index = 1; $index <= 26; $index++) {
            $order = $this->makeOrder(
                $reseller,
                OrderStatus::DISPATCHED,
                sprintf('ORD-PAGE-%02d', $index),
                'Paged Customer '.$index,
                '079'.str_pad((string) $index, 7, '0', STR_PAD_LEFT),
                ['product_name' => $index === 1 ? 'Paged Serum' : 'Paged Oil'],
            );
            if ($index === 1) {
                $this->makeShipment($order, $courier, 'WB-PAGE-1');
            }
        }

        $response = $this->actingAs($reseller)
            ->get(route('orders.index', ['search' => 'ORD-PAGE-01']))
            ->assertOk()
            ->assertSee('id="ongoingFilterSearch"', false)
            ->assertSee('id="ongoingFilterStatus"', false)
            ->assertSee('id="ongoingFilterProduct"', false)
            ->assertSee('id="ongoingFilterCourier"', false)
            ->assertSee('id="ongoingFilterDateFrom"', false)
            ->assertSee('id="ongoingFilterDateTo"', false)
            ->assertSee('data-per-page="25"', false)
            ->assertSee('data-page-nav', false)
            ->assertSee('value="PRINT"', false)
            ->assertSee('Paged Serum', false)
            ->assertSee('Paged Courier', false)
            ->assertSee('WB-PAGE-1');

        $content = $response->getContent();
        $orders = $this->ordersFrom($content);

        $this->assertCount(26, $orders);
        $this->assertSame(26, $this->summaryCount($content, 'ongoingSummaryDispatched'));
        $this->assertStringContainsString('order.order_number', $content);
        $this->assertStringContainsString('order.primary_phone', $content);
        $this->assertStringContainsString('order.waybill', $content);
        $this->assertStringContainsString('order.product', $content);

        $first = $this->row($orders, 'ORD-PAGE-01');
        $this->assertSame('Paged Customer 1', $first['customer_name']);
        $this->assertSame('WB-PAGE-1', $first['waybill']);
        $this->assertSame('Paged Serum / 30ml', $first['product']);
        $this->assertNotSame('', $first['product_id']);
        $this->assertSame((string) $courier->id, $first['courier_id']);

        $completed = $this->actingAs($reseller)
            ->get(route('orders.index', ['workspace' => 'archived']))
            ->assertOk();
        $completedContent = $completed->getContent();
        $this->assertStringContainsString('order.invoice_number', $completedContent);
        $this->assertStringContainsString('id="completedFilterOutcome"', $completedContent);
        $this->assertStringContainsString('id="completedFilterSettlement"', $completedContent);
        $this->assertStringContainsString('data-per-page="25"', $completedContent);
        $this->assertStringContainsString('data-page-nav', $completedContent);

        $archived = $this->actingAs($reseller)
            ->get(route('orders.index', ['workspace' => 'archived-orders']))
            ->assertOk();
        $archivedContent = $archived->getContent();
        $this->assertStringContainsString('order.invoice_number', $archivedContent);
        $this->assertStringContainsString('id="archivedFilterCourier"', $archivedContent);
        $this->assertStringContainsString('data-per-page="25"', $archivedContent);
        $this->assertStringContainsString('data-page-nav', $archivedContent);
    }

    public function test_workspace_pages_still_require_orders_view(): void
    {
        $reseller = $this->makeReseller(['products.view']);

        $this->actingAs($reseller)->get(route('orders.index'))->assertForbidden();
        $this->actingAs($reseller)
            ->get(route('orders.index', ['workspace' => 'archived']))
            ->assertForbidden();
        $this->actingAs($reseller)
            ->get(route('orders.index', ['workspace' => 'archived-orders']))
            ->assertForbidden();
    }

    private function useIsolatedDatabase(): void
    {
        if (extension_loaded('pdo_sqlite')) {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'database.connections.sqlite.prefix' => '',
                'database.connections.sqlite.foreign_key_constraints' => false,
            ]);
            DB::purge('sqlite');
            DB::reconnect('sqlite');
        } else {
            $database = 'dropshipping_reseller_workspace_test';
            $this->assertNotSame('dropshipping', $database);

            $pdo = new PDO(
                'mysql:host=127.0.0.1;port=3306',
                'root',
                'admin',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec(
                "CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );

            config([
                'database.default' => 'mysql',
                'database.connections.mysql.url' => null,
                'database.connections.mysql.host' => '127.0.0.1',
                'database.connections.mysql.port' => '3306',
                'database.connections.mysql.database' => $database,
                'database.connections.mysql.username' => 'root',
                'database.connections.mysql.password' => 'admin',
            ]);
            DB::purge('mysql');
            DB::reconnect('mysql');
        }

        $activeDatabase = (string) DB::connection()->getDatabaseName();

        $this->assertNotSame('dropshipping', $activeDatabase);
        $this->assertNotSame('', $activeDatabase);
    }

    private function createSchema(): void
    {
        Schema::dropAllTables();

        Schema::create('portals', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->string('subdomain')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('portal_id')->nullable();
            $table->unsignedBigInteger('owner_user_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->string('user_type')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('portal_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('portal_id')->nullable();
            $table->string('module')->nullable();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
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

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('order_number')->nullable();
            $table->string('source')->nullable();
            $table->string('order_type')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('market_id')->nullable();
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('market_code_snapshot')->nullable();
            $table->string('currency_code_snapshot')->nullable();
            $table->unsignedBigInteger('reseller_id')->nullable();
            $table->unsignedBigInteger('reseller_company_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('draft_courier_id')->nullable();
            $table->string('customer_name_snapshot')->nullable();
            $table->string('primary_phone_snapshot')->nullable();
            $table->unsignedBigInteger('primary_phone_country_id')->nullable();
            $table->decimal('items_subtotal', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('courier_fee_amount', 15, 2)->default(0);
            $table->decimal('customer_payable_amount', 15, 2)->default(0);
            $table->decimal('total_weight', 12, 3)->default(0);
            $table->boolean('after_hours')->default(false);
            $table->boolean('after_hours_warning_shown')->default(false);
            $table->decimal('after_hours_penalty_amount', 15, 2)->default(0);
            $table->boolean('duplicate_warning_shown')->default(false);
            $table->boolean('duplicate_warning_overridden')->default(false);
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->string('product_name_snapshot')->nullable();
            $table->string('variant_name_snapshot')->nullable();
            $table->string('barcode_snapshot')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_selling_price', 15, 2)->default(0);
            $table->decimal('unit_cost_snapshot', 15, 2)->default(0);
            $table->decimal('unit_company_commission_snapshot', 15, 2)->default(0);
            $table->decimal('unit_weight_snapshot', 12, 3)->default(0);
            $table->decimal('line_selling_total', 15, 2)->default(0);
            $table->decimal('line_weight_total', 12, 3)->default(0);
            $table->timestamps();
        });

        Schema::create('couriers', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('shipments', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid')->nullable();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('courier_id')->nullable();
            $table->string('tracking_number')->nullable();
            $table->decimal('weight_snapshot', 12, 3)->default(0);
            $table->decimal('courier_fee_snapshot', 15, 2)->default(0);
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('booked_at')->nullable();
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
            $table->decimal('net_payable', 15, 2)->default(0);
            $table->decimal('amount_paid', 15, 2)->default(0);
            $table->decimal('outstanding_balance', 15, 2)->default(0);
            $table->string('payment_status')->nullable();
            $table->string('instruction_status')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reseller_settlement_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 10)->nullable();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('earning_obligation_id')->nullable();
            $table->date('settlement_date')->nullable();
            $table->unsignedBigInteger('order_id');
            $table->string('order_number_snapshot')->nullable();
            $table->string('obligation_number_snapshot')->nullable();
            $table->date('business_date_snapshot')->nullable();
            $table->decimal('cod_amount_snapshot', 15, 2)->default(0);
            $table->decimal('service_charge_snapshot', 15, 2)->default(0);
            $table->decimal('courier_charge_snapshot', 15, 2)->default(0);
            $table->decimal('company_commission_snapshot', 15, 2)->default(0);
            $table->decimal('gross_reseller_profit', 15, 2)->default(0);
            $table->unsignedBigInteger('currency_id')->nullable();
            $table->string('currency_code_snapshot', 3)->nullable();
            $table->string('line_payment_status')->nullable();
            $table->timestamps();
        });
    }

    /**
     * @param  list<string>  $permissionSlugs
     */
    private function makeReseller(array $permissionSlugs): User
    {
        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'portal_id' => $this->portal->id,
            'name' => 'Reseller '.Str::lower(Str::random(4)),
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

        $role = Role::query()->create([
            'uuid' => (string) Str::uuid(),
            'portal_id' => $this->portal->id,
            'company_id' => null,
            'name' => 'Owner Test',
            'slug' => 'owner-test-'.Str::lower(Str::random(4)),
            'description' => 'Owner Test',
            'is_system' => false,
        ]);

        $permissionIds = [];
        foreach ($permissionSlugs as $slug) {
            $permission = Permission::query()->create([
                'uuid' => (string) Str::uuid(),
                'portal_id' => $this->portal->id,
                'module' => 'Orders',
                'name' => $slug,
                'slug' => $slug,
            ]);
            $permissionIds[] = $permission->id;
        }

        $role->permissions()->sync($permissionIds);
        $user->forceFill(['role_id' => $role->id])->save();

        return $user->fresh(['company', 'role']);
    }

    private function makeCourier(string $name): Courier
    {
        return Courier::query()->create([
            'code' => Str::lower(Str::random(8)),
            'name' => $name,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeOrder(
        User $reseller,
        OrderStatus $status,
        string $orderNumber,
        string $customerName,
        string $phone,
        array $overrides = [],
    ): Order {
        $productName = (string) ($overrides['product_name'] ?? 'Serum');
        unset($overrides['product_name']);

        $order = Order::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'order_number' => $orderNumber,
            'source' => OrderSource::MANUAL,
            'status' => $status,
            'market_id' => 1,
            'currency_id' => 1,
            'market_code_snapshot' => 'lk',
            'currency_code_snapshot' => 'LKR',
            'reseller_id' => $reseller->id,
            'reseller_company_id' => $reseller->company_id,
            'supplier_id' => $reseller->id,
            'customer_id' => 1,
            'customer_name_snapshot' => $customerName,
            'primary_phone_snapshot' => $phone,
            'primary_phone_country_id' => 1,
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
            'product_id' => 10 + (int) $order->id,
            'product_variant_id' => 1,
            'product_name_snapshot' => $productName,
            'variant_name_snapshot' => '30ml',
            'barcode_snapshot' => 'BC'.$order->id,
            'quantity' => 1,
            'unit_selling_price' => 250,
            'unit_cost_snapshot' => 100,
            'unit_company_commission_snapshot' => 150,
            'unit_weight_snapshot' => 0.5,
            'line_selling_total' => 250,
            'line_weight_total' => 0.5,
        ]);

        return $order->fresh();
    }

    private function makeShipment(Order $order, Courier $courier, ?string $trackingNumber): Shipment
    {
        return Shipment::query()->create([
            'order_id' => $order->id,
            'supplier_id' => $order->supplier_id,
            'courier_id' => $courier->id,
            'tracking_number' => $trackingNumber,
            'weight_snapshot' => 0.5,
            'courier_fee_snapshot' => 0,
            'currency_id' => 1,
            'status' => ShipmentStatus::BOOKED,
            'booked_at' => now(),
        ]);
    }

    private function makeSettlementLine(
        Order $order,
        string $invoiceNumber,
        string $lineStatus,
        string $instructionStatus = ResellerSettlementInvoice::INSTRUCTION_STATUS_ACTIVE,
    ): void {
        $invoice = ResellerSettlementInvoice::query()->create([
            'invoice_number' => $invoiceNumber,
            'batch_id' => 1,
            'reseller_id' => $order->reseller_id,
            'reseller_company_id' => $order->reseller_company_id,
            'reseller_name_snapshot' => 'Reseller',
            'currency_id' => 1,
            'currency_code_snapshot' => 'LKR',
            'gross_earnings_total' => 100,
            'debt_deduction_total' => 0,
            'adjustment_total' => 0,
            'net_payable' => 100,
            'amount_paid' => $lineStatus === ResellerSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID ? 100 : 0,
            'outstanding_balance' => $lineStatus === ResellerSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID ? 0 : 100,
            'payment_status' => $lineStatus === ResellerSettlementInvoiceLine::LINE_PAYMENT_STATUS_PAID
                ? ResellerSettlementInvoice::PAYMENT_STATUS_PAID
                : ResellerSettlementInvoice::PAYMENT_STATUS_UNPAID,
            'instruction_status' => $instructionStatus,
            'issued_at' => now(),
        ]);

        ResellerSettlementInvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'earning_obligation_id' => $invoice->id,
            'settlement_date' => now()->toDateString(),
            'order_id' => $order->id,
            'order_number_snapshot' => $order->order_number,
            'obligation_number_snapshot' => 'OBL-'.$invoice->id,
            'business_date_snapshot' => now()->toDateString(),
            'cod_amount_snapshot' => 250,
            'service_charge_snapshot' => 0,
            'courier_charge_snapshot' => 0,
            'company_commission_snapshot' => 0,
            'gross_reseller_profit' => 100,
            'currency_id' => 1,
            'currency_code_snapshot' => 'LKR',
            'line_payment_status' => $lineStatus,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ordersFrom(string $html): array
    {
        $this->assertSame(1, preg_match("/data-orders='([^']*)'/", $html, $matches));

        $orders = json_decode($matches[1], true);

        $this->assertIsArray($orders);

        return $orders;
    }

    /**
     * @param  list<array<string, mixed>>  $orders
     * @return array<string, mixed>
     */
    private function row(array $orders, string $orderNumber): array
    {
        foreach ($orders as $order) {
            if (($order['order_number'] ?? null) === $orderNumber) {
                return $order;
            }
        }

        $this->fail('Order '.$orderNumber.' was not in the workspace payload.');
    }

    private function summaryCount(string $html, string $elementId): int
    {
        $this->assertSame(
            1,
            preg_match('/id="'.preg_quote($elementId, '/').'">([^<]*)</', $html, $matches)
        );

        return (int) str_replace(',', '', trim($matches[1]));
    }
}
