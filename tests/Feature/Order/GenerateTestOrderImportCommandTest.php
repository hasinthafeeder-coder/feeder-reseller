<?php

namespace Tests\Feature\Order;

use App\Services\Order\TestOrderImportGeneratorService;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\ProductCategory;
use Feeder\Core\Models\ProductVariant;
use Feeder\Core\Models\ResellerSupplierAssignment;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Order\OrderImportFileParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class GenerateTestOrderImportCommandTest extends TestCase
{
    use SetsUpMarketData;
    use UsesMysqlTestDatabase;

    private string $outputPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedMarketLookups();
        config(['cache.default' => 'array']);

        $this->outputPath = storage_path('app/testing/test-import-orders-'.Str::lower(Str::random(8)).'.xlsx');
    }

    protected function tearDown(): void
    {
        if (is_file($this->outputPath)) {
            @unlink($this->outputPath);
        }

        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_command_generates_valid_xlsx_from_real_variants(): void
    {
        $phone = '0799'.random_int(100001, 999998);
        $reseller = $this->makeReseller($phone);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);

        $locked = $this->makeVariant($supplier, [
            'selling_price' => 2500.00,
            'cost' => 1000.00,
            'company_commission' => 150.00,
        ], true);

        $unlocked = $this->makeVariant($supplier, [
            'selling_price' => 2500.00,
            'suggested_price_min' => 2000.00,
            'suggested_price_max' => 3000.00,
            'cost' => 1000.00,
            'company_commission' => 150.00,
        ], false);

        $exit = Artisan::call('orders:generate-test-import', [
            '--rows' => 6,
            '--output' => $this->outputPath,
            '--reseller-phone' => $phone,
        ]);

        $this->assertSame(0, $exit, Artisan::output());
        $this->assertFileExists($this->outputPath);

        $parsed = app(OrderImportFileParser::class)->parse(
            new UploadedFile($this->outputPath, 'test-import-orders.xlsx', null, null, true)
        );

        $this->assertSame('xlsx', $parsed['file_type']);
        $this->assertCount(6, $parsed['rows']);

        $itemCodes = collect($parsed['rows'])->pluck('item_code')->map(fn ($v) => (int) $v)->all();
        $this->assertContains((int) $locked->id, $itemCodes);
        $this->assertContains((int) $unlocked->id, $itemCodes);

        foreach ($parsed['rows'] as $row) {
            foreach (OrderImportFileParser::REQUIRED_HEADERS as $header) {
                $this->assertArrayHasKey($header, $row);
            }
            $this->assertNotSame('', $row['name']);
            $this->assertNotSame('', $row['address']);
            $this->assertMatchesRegularExpression('/^070923\d{4}$/', $row['tp_1']);
            $this->assertTrue(ctype_digit($row['item_code']));
            $this->assertContains((int) $row['qty'], [1, 2, 3]);
            $this->assertContains((int) $row['delivery'], [300, 350, 400, 450]);
        }

        $upload = $this->actingAs($reseller)->postJson(route('orders.import.upload'), [
            'file' => new UploadedFile(
                $this->outputPath,
                'test-import-orders.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                null,
                true
            ),
        ]);

        $upload->assertOk();
        $this->assertSame(6, $upload->json('data.summary.valid_rows'));
        $this->assertSame(0, $upload->json('data.summary.invalid_rows'));
        $this->assertSame(0, $upload->json('data.summary.banned_rows'));
    }

    public function test_generator_service_rejects_missing_reseller(): void
    {
        $this->expectException(\RuntimeException::class);

        app(TestOrderImportGeneratorService::class)->generate(
            2,
            $this->outputPath,
            '0700000099',
        );
    }

    private function makeReseller(string $phone): User
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
            'phone' => $phone,
            'registration_number' => 'REG-'.Str::random(6),
            'status' => CompanyStatus::ACTIVE->value,
        ]);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'email' => $company->email,
            'phone' => $phone,
            'password' => Hash::make('password'),
            'user_type' => UserType::OWNER->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
        ]);

        $company->forceFill(['owner_user_id' => $user->id])->save();
        $this->configureResellerCompany($company, ['lk']);

        $role = Role::query()->create([
            'uuid' => (string) Str::uuid(),
            'portal_id' => $portal->id,
            'company_id' => null,
            'slug' => 'owner-import-gen-'.Str::lower(Str::random(6)),
            'name' => 'Owner Import Generator Test',
            'description' => 'Owner Import Generator Test',
            'is_system' => false,
        ]);

        $permission = Permission::query()->firstOrCreate(
            [
                'portal_id' => $portal->id,
                'slug' => 'orders.create',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'module' => 'Orders',
                'group' => 'Orders',
                'name' => 'orders.create',
                'description' => null,
                'sort_order' => 10,
            ]
        );

        $role->permissions()->sync([$permission->id]);
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
    private function makeVariant(User $supplier, array $variantOverrides, bool $priceLocked): ProductVariant
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
            'price_locked' => $priceLocked,
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
}
