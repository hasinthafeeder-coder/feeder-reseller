<?php

namespace Tests\Feature\Order;

use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\OrderPaymentMethod;
use Feeder\Core\Enums\OrderPaymentReviewStatus;
use Feeder\Core\Enums\OrderSource;
use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Courier;
use Feeder\Core\Models\CourierCity;
use Feeder\Core\Models\CourierMarketPricing;
use Feeder\Core\Models\CourierService;
use Feeder\Core\Models\Customer;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\OrderAddress;
use Feeder\Core\Models\OrderItem;
use Feeder\Core\Models\OrderPaymentSubmission;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\ProductCategory;
use Feeder\Core\Models\ProductVariant;
use Feeder\Core\Models\ResellerSupplierAssignment;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\SupplierCourierAccount;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Order\OrderPaymentReviewService;
use Feeder\Core\Services\UuidService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class ResellerBankTransferUiTest extends TestCase
{
    use SetsUpMarketData;
    use UsesMysqlTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedMarketLookups();
        config([
            'cache.default' => 'array',
            'feeder.file_server.url' => 'http://files.test',
            'feeder.file_server.api_key' => 'test-file-key',
        ]);

        Http::fake([
            'files.test/*' => Http::response([
                'message' => 'File uploaded successfully.',
                'file' => [
                    'uuid' => 'UIPROOF001',
                    'application' => 'RESELLER',
                    'entity_type' => 'ORDER_PAYMENT',
                    'entity_uuid' => 'ENTITY0001',
                    'category' => 'PAYMENT_PROOF',
                    'original_name' => 'slip.pdf',
                    'mime_type' => 'application/pdf',
                    'size' => 120,
                ],
            ], 201),
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_show_page_exposes_bank_transfer_fields_and_request_approval(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.update']);
        $order = $this->makeReadyOrder($actor);

        $this->actingAs($actor)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Bank transfer')
            ->assertSee('Cash on delivery')
            ->assertSee('Request Approval')
            ->assertSee('Payment proof')
            ->assertSee('bankTransferConfirmModal', false);
    }

    public function test_valid_bank_transfer_submission_creates_pending_approval_ui_state(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.update', 'orders.status.update', 'orders.shipment.book']);
        $order = $this->makeReadyOrder($actor);

        $this->actingAs($actor)
            ->post(route('orders.payment.bank-transfer', $order), [
                'payment_slip' => UploadedFile::fake()->create('slip.pdf', 120, 'application/pdf'),
                'reference_number' => 'BT-REF-100',
                'amount' => 350.50,
                'description' => 'Paid via bank transfer',
            ])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('success');

        $submission = OrderPaymentSubmission::query()->where('order_id', $order->id)->latest('id')->first();
        $this->assertNotNull($submission);
        $this->assertSame(OrderPaymentMethod::BANK_TRANSFER, $submission->method);
        $this->assertSame(OrderPaymentReviewStatus::PENDING_REVIEW, $submission->review_status);
        $this->assertSame('UIPROOF001', $submission->slip_file_uuid);
        $this->assertNull($submission->slip_path);

        $show = $this->actingAs($actor)->get(route('orders.show', $order));
        $show->assertOk()
            ->assertSee('Pending Approval')
            ->assertSee('BT-REF-100')
            ->assertSee('This order is Pending Approval')
            ->assertDontSee('id="orderBankTransferForm"', false)
            ->assertDontSee('id="requestBankTransferApprovalBtn"', false)
            ->assertDontSee('id="manualOrderForm"', false)
            ->assertDontSee('Change Status');
    }

    public function test_pending_approval_appears_in_reseller_status_filter(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.update']);
        $order = $this->makeReadyOrder($actor);
        $this->submitBankTransfer($actor, $order);

        $index = $this->actingAs($actor)->get(route('orders.index'));
        $index->assertOk()->assertSee('Pending Approval');

        $filtered = $this->actingAs($actor)
            ->get(route('orders.index', ['status' => 'PENDING_APPROVAL']));
        $filtered->assertOk()->assertSee($order->order_number);
    }

    public function test_pending_approval_blocks_status_cancel_and_second_submission(): void
    {
        $actor = $this->makeResellerWithPermission([
            'orders.view',
            'orders.update',
            'orders.status.update',
            'orders.shipment.book',
        ]);
        $order = $this->makeReadyOrder($actor);
        $this->submitBankTransfer($actor, $order);

        $this->actingAs($actor)
            ->post(route('orders.status.update', $order), [
                'status' => OrderStatus::CANCELLED->value,
            ])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('order');

        $this->actingAs($actor)
            ->post(route('orders.payment.bank-transfer', $order), [
                'payment_slip' => UploadedFile::fake()->create('slip2.pdf', 120, 'application/pdf'),
                'reference_number' => 'BT-REF-200',
                'amount' => 350.50,
                'description' => 'Second attempt should fail',
            ])
            ->assertRedirect(route('orders.show', $order))
            ->assertSessionHasErrors('payment');

        $this->assertSame(1, OrderPaymentSubmission::query()->where('order_id', $order->id)->count());
        $this->assertSame(OrderStatus::PENDING, $order->fresh()->status);
    }

    public function test_rejected_payment_shows_reason_and_allows_new_submission(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.update']);
        $order = $this->makeReadyOrder($actor);
        $first = $this->submitBankTransfer($actor, $order);

        $admin = $this->makeAdminReviewer();
        app(OrderPaymentReviewService::class)->reject($first, $admin, 'Blurry slip image');

        $show = $this->actingAs($actor)->get(route('orders.show', $order));
        $show->assertOk()
            ->assertSee('Payment rejected')
            ->assertSee('Blurry slip image')
            ->assertSee('Request Approval again');

        Http::fake([
            'files.test/*' => Http::response([
                'message' => 'File uploaded successfully.',
                'file' => [
                    'uuid' => 'UIPROOF002',
                    'application' => 'RESELLER',
                    'entity_type' => 'ORDER_PAYMENT',
                    'entity_uuid' => 'ENTITY0002',
                    'category' => 'PAYMENT_PROOF',
                    'original_name' => 'slip-retry.pdf',
                    'mime_type' => 'application/pdf',
                    'size' => 140,
                ],
            ], 201),
        ]);

        $this->actingAs($actor)
            ->post(route('orders.payment.bank-transfer', $order), [
                'payment_slip' => UploadedFile::fake()->create('slip-retry.pdf', 140, 'application/pdf'),
                'reference_number' => 'BT-REF-RETRY',
                'amount' => 360,
                'description' => 'Resubmitted clear slip',
            ])
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame(2, OrderPaymentSubmission::query()->where('order_id', $order->id)->count());
        $this->assertSame(OrderPaymentReviewStatus::REJECTED, $first->fresh()->review_status);
        $this->assertSame('Blurry slip image', $first->fresh()->review_note);
        $this->assertSame('UIPROOF001', $first->fresh()->slip_file_uuid);
    }

    public function test_cod_order_show_still_works_without_payment_submission(): void
    {
        $actor = $this->makeResellerWithPermission(['orders.view', 'orders.update', 'orders.status.update']);
        $order = $this->makeReadyOrder($actor);

        $this->actingAs($actor)
            ->get(route('orders.show', $order))
            ->assertOk()
            ->assertSee('Cash on delivery')
            ->assertDontSee('This order is Pending Approval')
            ->assertDontSee('Payment rejected');

        $this->actingAs($actor)
            ->post(route('orders.status.update', $order), [
                'status' => OrderStatus::HOLD->value,
            ])
            ->assertRedirect(route('orders.show', $order));

        $this->assertSame(OrderStatus::HOLD, $order->fresh()->status);
        $this->assertSame(0, OrderPaymentSubmission::query()->where('order_id', $order->id)->count());
    }

    private function submitBankTransfer(User $actor, Order $order): OrderPaymentSubmission
    {
        $this->actingAs($actor)
            ->post(route('orders.payment.bank-transfer', $order), [
                'payment_slip' => UploadedFile::fake()->create('slip.pdf', 120, 'application/pdf'),
                'reference_number' => 'BT-REF-100',
                'amount' => 350.50,
                'description' => 'Paid via bank transfer',
            ])
            ->assertRedirect(route('orders.show', $order));

        return OrderPaymentSubmission::query()->where('order_id', $order->id)->latest('id')->firstOrFail();
    }

    private function makeReadyOrder(User $actor): Order
    {
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($actor, $supplier);
        $variant = $this->makeVariant($supplier);
        $order = $this->makeOrder($actor, $supplier, $variant);
        $setup = $this->makeCourierSetup($order);

        $order->forceFill([
            'draft_courier_id' => $setup['courier']->id,
            'draft_courier_service_id' => $setup['service']->id,
            'draft_courier_city_id' => $setup['city']->id,
        ])->save();

        return $order->fresh();
    }

    /**
     * @param  list<string>  $permissionSlugs
     */
    private function makeResellerWithPermission(array $permissionSlugs, ?Company $company = null): User
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

        $company ??= Company::query()->create([
            'uuid' => UuidService::generate(),
            'portal_id' => $portal->id,
            'name' => 'Reseller Co '.Str::lower(Str::random(4)),
            'email' => 'reseller-'.Str::uuid().'@feeder.local',
            'phone' => '077'.random_int(100000, 999999),
            'registration_number' => 'REG-'.Str::random(6),
            'status' => CompanyStatus::ACTIVE->value,
        ]);

        $user = User::query()->create([
            'uuid' => UuidService::generate(),
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

        $role = Role::query()->create([
            'uuid' => UuidService::generate(),
            'portal_id' => $portal->id,
            'company_id' => null,
            'slug' => 'owner-bt-'.Str::lower(Str::random(6)),
            'name' => 'Owner BT',
            'description' => 'Owner BT',
            'is_system' => false,
        ]);

        $permissionIds = [];
        foreach ($permissionSlugs as $slug) {
            $permission = Permission::query()->firstOrCreate(
                [
                    'portal_id' => $portal->id,
                    'slug' => $slug,
                ],
                [
                    'uuid' => UuidService::generate(),
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

    private function makeAdminReviewer(): User
    {
        $portal = Portal::query()->firstOrCreate(
            ['code' => PortalCode::ADMIN->value],
            [
                'uuid' => UuidService::generate(),
                'name' => 'Admin Portal',
                'subdomain' => 'admin-'.Str::lower(Str::random(4)),
                'description' => 'Admin Portal',
                'is_active' => true,
            ]
        );

        $role = Role::query()->create([
            'uuid' => UuidService::generate(),
            'portal_id' => $portal->id,
            'company_id' => null,
            'slug' => 'admin-bt-'.Str::lower(Str::random(6)),
            'name' => 'Admin BT',
            'description' => 'Admin BT',
            'is_system' => false,
        ]);

        $permission = Permission::query()->firstOrCreate(
            [
                'portal_id' => $portal->id,
                'slug' => OrderPaymentReviewService::PERMISSION_REJECT,
            ],
            [
                'uuid' => UuidService::generate(),
                'module' => 'Orders',
                'group' => 'Orders',
                'name' => OrderPaymentReviewService::PERMISSION_REJECT,
                'description' => null,
                'sort_order' => 10,
            ]
        );
        $role->permissions()->sync([$permission->id]);

        return User::query()->create([
            'uuid' => UuidService::generate(),
            'company_id' => null,
            'role_id' => $role->id,
            'email' => 'admin-'.Str::lower(Str::random(6)).'@feeder.local',
            'phone' => '071'.random_int(1000000, 9999999),
            'password' => Hash::make('password'),
            'user_type' => UserType::OWNER->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
        ])->fresh(['role']);
    }

    private function makeSupplierUser(): User
    {
        $portal = Portal::query()->firstOrCreate(
            ['code' => PortalCode::SUPPLIER->value],
            [
                'uuid' => UuidService::generate(),
                'name' => 'Supplier Portal',
                'subdomain' => 'supplier-'.Str::lower(Str::random(4)),
                'description' => 'Supplier Portal',
                'is_active' => true,
            ]
        );

        $company = Company::query()->create([
            'uuid' => UuidService::generate(),
            'portal_id' => $portal->id,
            'name' => 'Supplier Co '.Str::lower(Str::random(4)),
            'email' => 'supplier-'.Str::uuid().'@feeder.local',
            'phone' => '076'.random_int(100000, 999999),
            'registration_number' => 'SUP-'.Str::random(6),
            'status' => CompanyStatus::ACTIVE->value,
            'operation_market_id' => $this->marketByCode('lk')->id,
        ]);

        $user = User::query()->create([
            'uuid' => UuidService::generate(),
            'company_id' => $company->id,
            'email' => 'supplier-user-'.Str::uuid().'@feeder.local',
            'phone' => '076'.random_int(100000, 999999),
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
        ResellerSupplierAssignment::query()->create([
            'reseller_id' => $reseller->id,
            'supplier_id' => $supplier->id,
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

    private function makeOrder(User $reseller, User $supplier, ProductVariant $variant): Order
    {
        $market = $this->marketByCode('lk');
        $customer = Customer::query()->create([
            'uuid' => UuidService::generate(),
            'display_name' => 'BT Customer',
            'primary_country_id' => $this->countryByIso('LK')->id,
            'is_banned' => false,
        ]);

        $order = Order::query()->create([
            'uuid' => (string) Str::uuid(),
            'order_number' => 'ORD-BT-'.Str::upper(Str::random(5)),
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
            'customer_name_snapshot' => 'BT Customer',
            'primary_phone_snapshot' => '070'.random_int(1000000, 9999999),
            'primary_phone_country_id' => $this->countryByIso('LK')->id,
            'items_subtotal' => 250,
            'discount_amount' => 0,
            'courier_fee_amount' => 0,
            'customer_payable_amount' => 250,
            'total_weight' => 0.5,
            'created_by' => $reseller->id,
            'updated_by' => $reseller->id,
        ]);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $variant->product_id,
            'product_variant_id' => $variant->id,
            'product_name_snapshot' => 'BT UI Product',
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

        OrderAddress::query()->create([
            'order_id' => $order->id,
            'recipient_name' => 'BT Customer',
            'line1' => '1 Bank Road',
            'city_name' => 'Colombo',
            'country_id' => $this->countryByIso('LK')->id,
        ]);

        return $order->fresh();
    }

    /**
     * @return array{courier: Courier, service: CourierService, city: CourierCity}
     */
    private function makeCourierSetup(Order $order): array
    {
        $courier = Courier::query()->create([
            'code' => 'BT'.strtoupper(substr(uniqid(), -4)),
            'name' => 'BT Courier',
            'is_active' => true,
        ]);

        $service = CourierService::query()->create([
            'courier_id' => $courier->id,
            'code' => 'STD',
            'name' => 'Standard',
            'external_service_id' => 'ext-std',
            'is_active' => true,
        ]);

        $city = CourierCity::query()->create([
            'courier_id' => $courier->id,
            'district_name' => 'Colombo',
            'city_name' => 'Colombo 03',
            'external_city_code' => 'CMB'.Str::upper(Str::random(4)),
            'external_district_code' => 'COL',
            'is_active' => true,
        ]);

        CourierMarketPricing::query()->create([
            'courier_id' => $courier->id,
            'market_id' => $order->market_id,
            'currency_id' => $order->currency_id,
            'first_kg_fee' => 600.00,
            'additional_kg_fee' => 100.00,
            'is_active' => true,
        ]);

        SupplierCourierAccount::query()->create([
            'supplier_id' => $order->supplier_id,
            'courier_id' => $courier->id,
            'account_label' => 'Primary',
            'credentials_encrypted' => Crypt::encryptString(json_encode(['api_key' => 'secret'], JSON_THROW_ON_ERROR)),
            'is_active' => true,
        ]);

        return compact('courier', 'service', 'city');
    }
}
