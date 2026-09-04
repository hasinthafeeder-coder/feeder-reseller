<?php

namespace Tests\Feature\Product;

use App\Models\User;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Enums\SupplierType;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\ProductCategory;
use Feeder\Core\Models\ProductDescription;
use Feeder\Core\Models\ProductVariant;
use Feeder\Core\Models\ResellerSupplierAssignment;
use Feeder\Core\Models\Role;
use Feeder\Core\Services\GoodsReceivedNoteService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class ResellerProductDetailTest extends TestCase
{
    use SetsUpMarketData;
    use UsesMysqlTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedMarketLookups();
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_assigned_supplier_product_details_are_accessible(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('Supplier A');
        $this->assignSupplier($reseller, $supplier);

        $product = $this->makeProduct($supplier, $category, 'Accessible Product');

        $this->actingAs($reseller)
            ->get(route('products.details', $product))
            ->assertOk()
            ->assertSee('Accessible Product')
            ->assertSee('Supplier A');
    }

    public function test_unassigned_supplier_product_returns_not_found(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $assignedSupplier = $this->makeSupplierUser('Assigned Supplier');
        $unassignedSupplier = $this->makeSupplierUser('Hidden Supplier');

        $this->assignSupplier($reseller, $assignedSupplier);

        $hiddenProduct = $this->makeProduct($unassignedSupplier, $category, 'Hidden Product');

        $this->actingAs($reseller)
            ->get(route('products.details', $hiddenProduct))
            ->assertNotFound();
    }

    public function test_pro_supplier_badge_is_displayed_on_details(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('Pro Supplier', SupplierType::PRO);
        $this->assignSupplier($reseller, $supplier);

        $product = $this->makeProduct($supplier, $category, 'Pro Detail Product');

        $this->actingAs($reseller)
            ->get(route('products.details', $product))
            ->assertOk()
            ->assertSee('>PRO</span>', false);
    }

    public function test_standard_supplier_does_not_show_pro_badge_on_details(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('Standard Supplier', SupplierType::STANDARD);
        $this->assignSupplier($reseller, $supplier);

        $product = $this->makeProduct($supplier, $category, 'Standard Detail Product');

        $response = $this->actingAs($reseller)
            ->get(route('products.details', $product))
            ->assertOk()
            ->assertSee('Standard Detail Product');

        $this->assertSame(0, substr_count($response->getContent(), '>PRO</span>'));
    }

    public function test_price_locked_product_shows_selling_price_on_details(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('Supplier A');
        $this->assignSupplier($reseller, $supplier);

        $product = $this->makeProduct(
            $supplier,
            $category,
            'Locked Detail Product',
            priceLocked: true,
            cost: 1000,
            companyCommission: 150,
            sellingPrice: 2500,
        );

        $this->actingAs($reseller)
            ->get(route('products.details', $product))
            ->assertOk()
            ->assertSee('Selling Price')
            ->assertSee('LKR 2,500.00')
            ->assertSee('LKR 1,150.00')
            ->assertSee('LKR 1,350.00')
            ->assertSee('Your Commission');
    }

    public function test_unlocked_product_shows_suggested_range_on_details(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('Supplier A');
        $this->assignSupplier($reseller, $supplier);

        $product = $this->makeProduct(
            $supplier,
            $category,
            'Unlocked Detail Product',
            priceLocked: false,
            cost: 1000,
            companyCommission: 150,
            suggestedPriceMin: 2500,
            suggestedPriceMax: 3500,
        );

        $this->actingAs($reseller)
            ->get(route('products.details', $product))
            ->assertOk()
            ->assertSee('Suggested Price')
            ->assertSee('LKR 2,500.00')
            ->assertSee('LKR 3,500.00')
            ->assertSee('Potential Commission');
    }

    public function test_zero_stock_product_remains_accessible_on_details(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('Supplier A');
        $this->assignSupplier($reseller, $supplier);

        $product = $this->makeProduct(
            $supplier,
            $category,
            'Zero Stock Product',
            stockQuantity: 0,
        );

        $this->actingAs($reseller)
            ->get(route('products.details', $product))
            ->assertOk()
            ->assertSee('Zero Stock Product')
            ->assertSee('Out of Stock');
    }

    public function test_product_descriptions_display_all_market_languages(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('Supplier A');
        $this->assignSupplier($reseller, $supplier);

        $product = $this->makeProduct(
            $supplier,
            $category,
            'Trilingual Product',
            descriptions: [
                'en' => 'English product description',
                'si' => 'Sinhala product description',
                'ta' => 'Tamil product description',
            ],
        );

        $this->actingAs($reseller)
            ->get(route('products.details', $product))
            ->assertOk()
            ->assertSee('English')
            ->assertSee('English product description')
            ->assertSee('සිංහල')
            ->assertSee('Sinhala product description')
            ->assertSee('தமிழ்')
            ->assertSee('Tamil product description');
    }

    public function test_missing_language_description_is_handled_gracefully(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('Supplier A');
        $this->assignSupplier($reseller, $supplier);

        $product = $this->makeProduct(
            $supplier,
            $category,
            'Partial Description Product',
            descriptions: [
                'en' => 'English only description',
            ],
        );

        $this->actingAs($reseller)
            ->get(route('products.details', $product))
            ->assertOk()
            ->assertSee('English only description')
            ->assertSee('No Sinhala description available.')
            ->assertSee('No Tamil description available.');
    }

    public function test_malaysian_market_product_uses_myr_currency_on_details(): void
    {
        $reseller = $this->makeResellerUser(['my']);
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('MY Supplier', marketCode: 'my');
        $this->assignSupplier($reseller, $supplier);

        $product = $this->makeProduct(
            $supplier,
            $category,
            'Malaysia Product',
            marketCode: 'my',
            priceLocked: true,
            cost: 100,
            companyCommission: 20,
            sellingPrice: 200,
        );

        $this->actingAs($reseller)
            ->get(route('products.details', $product))
            ->assertOk()
            ->assertSee('Malaysia Product')
            ->assertSee('MYR 200.00')
            ->assertSee('MYR 120.00');
    }

    public function test_multi_variant_product_displays_variant_options(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('Supplier A');
        $this->assignSupplier($reseller, $supplier);

        $product = $this->makeProduct(
            $supplier,
            $category,
            'Multi Variant Product',
            priceLocked: true,
            cost: 1000,
            companyCommission: 100,
            sellingPrice: 2000,
            extraVariants: [
                [
                    'name' => 'Blue',
                    'selling_price' => 2200,
                    'cost' => 1100,
                    'company_commission' => 100,
                ],
            ],
        );

        $response = $this->actingAs($reseller)
            ->get(route('products.details', $product))
            ->assertOk()
            ->assertSee('Multi Variant Product')
            ->assertSee('Default')
            ->assertSee('Blue')
            ->assertSee('LKR 2,000.00')
            ->assertSee('Variant ID:');

        $this->assertStringContainsString('"selling_price":2200', $response->getContent());
        $this->assertStringContainsString((string) $product->variants->first()->id, $response->getContent());
        $this->assertStringContainsString((string) $product->variants->last()->id, $response->getContent());
    }

    public function test_single_variant_product_displays_variant_id(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('Supplier A');
        $this->assignSupplier($reseller, $supplier);

        $product = $this->makeProduct($supplier, $category, 'Single Variant Product');
        $variantId = $product->variants->first()->id;

        $this->actingAs($reseller)
            ->get(route('products.details', $product))
            ->assertOk()
            ->assertSee('Variant ID:')
            ->assertSee((string) $variantId);
    }

    public function test_specifications_section_is_not_rendered(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('Supplier A');
        $this->assignSupplier($reseller, $supplier);

        $product = $this->makeProduct($supplier, $category, 'No Specs Product');

        $response = $this->actingAs($reseller)
            ->get(route('products.details', $product))
            ->assertOk();

        $this->assertStringNotContainsString('info-specifications', $response->getContent());
        $this->assertStringNotContainsString('Specifications', $response->getContent());
    }

    private function makeResellerUser(array $marketCodes = ['lk']): User
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
        $this->configureResellerCompany($company, $marketCodes);

        $role = $this->ensureResellerOwnerRole($portal);
        $user->forceFill(['role_id' => $role->id])->save();

        return $user->fresh(['company', 'role']);
    }

    private function ensureResellerOwnerRole(Portal $portal): Role
    {
        $permission = Permission::query()->firstOrCreate(
            [
                'portal_id' => $portal->id,
                'slug' => 'products.view',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'module' => 'Products',
                'group' => 'Products',
                'name' => 'View Products',
                'description' => null,
                'sort_order' => 10,
            ]
        );

        $role = Role::query()->firstOrCreate(
            [
                'portal_id' => $portal->id,
                'slug' => 'owner',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'company_id' => null,
                'name' => 'Owner',
                'description' => 'Owner',
                'is_system' => true,
            ]
        );

        $role->permissions()->syncWithoutDetaching([$permission->id]);

        return $role;
    }

    private function makeSupplierUser(
        string $companyName,
        SupplierType $supplierType = SupplierType::STANDARD,
        string $marketCode = 'lk',
    ): User {
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
            'name' => $companyName,
            'email' => Str::slug($companyName).'-'.Str::uuid().'@feeder.local',
            'phone' => '077'.random_int(100000, 999999),
            'registration_number' => 'REG-'.Str::random(6),
            'status' => CompanyStatus::ACTIVE->value,
            'supplier_type' => $supplierType,
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
        $this->configureSupplierCompany($company, $marketCode);

        return $user->fresh('company');
    }

    private function assignSupplier(User $reseller, User $supplier): void
    {
        ResellerSupplierAssignment::query()->create([
            'reseller_id' => $reseller->id,
            'supplier_id' => $supplier->id,
        ]);
    }

    private function makeCategory(string $name, ?ProductCategory $parent = null): ProductCategory
    {
        return ProductCategory::query()->create([
            'id' => (string) Str::uuid(),
            'parent_id' => $parent?->id,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, string>  $descriptions
     * @param  list<array<string, mixed>>  $extraVariants
     */
    private function makeProduct(
        User $supplier,
        ProductCategory $category,
        string $productName,
        bool $priceLocked = false,
        float $cost = 1000,
        float $companyCommission = 150,
        ?float $sellingPrice = 1500,
        ?float $suggestedPriceMin = null,
        ?float $suggestedPriceMax = null,
        int $stockQuantity = 10,
        string $marketCode = 'lk',
        array $descriptions = [],
        array $extraVariants = [],
    ): Product {
        $product = Product::query()->create([
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'market_id' => $this->marketByCode($marketCode)->id,
            'name' => $productName,
            'slug' => Str::slug($productName).'-'.Str::lower(Str::random(6)),
            'status' => ProductStatus::ACTIVE,
            'system_visible' => true,
            'web_visible' => true,
            'price_locked' => $priceLocked,
            'published_at' => now(),
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ]);

        $descriptionData = $descriptions !== []
            ? $descriptions
            : ['en' => $productName.' description'];

        foreach ($descriptionData as $languageCode => $description) {
            ProductDescription::query()->create([
                'product_id' => $product->id,
                'language_code' => $languageCode,
                'description' => $description,
            ]);
        }

        $variantDefinitions = array_merge([
            [
                'name' => 'Default',
                'cost' => $cost,
                'company_commission' => $companyCommission,
                'selling_price' => $sellingPrice,
                'suggested_price_min' => $suggestedPriceMin,
                'suggested_price_max' => $suggestedPriceMax,
                'stock_quantity' => $stockQuantity,
            ],
        ], $extraVariants);

        foreach ($variantDefinitions as $index => $definition) {
            $variant = ProductVariant::query()->create([
                'uuid' => (string) Str::uuid(),
                'product_id' => $product->id,
                'name' => $definition['name'],
                'barcode' => 'BC-'.strtoupper(Str::random(8)),
                'cost' => $definition['cost'] ?? $cost,
                'selling_price' => $definition['selling_price'] ?? $sellingPrice,
                'suggested_price_min' => $definition['suggested_price_min'] ?? $suggestedPriceMin,
                'suggested_price_max' => $definition['suggested_price_max'] ?? $suggestedPriceMax,
                'weight' => 0.500,
                'company_commission' => $definition['company_commission'] ?? $companyCommission,
                'sort_order' => $index,
                'is_active' => true,
                'created_by' => $supplier->id,
                'updated_by' => $supplier->id,
            ]);

            $variantStock = (int) ($definition['stock_quantity'] ?? $stockQuantity);

            if ($variantStock > 0) {
                app(GoodsReceivedNoteService::class)->createGrn(
                    $supplier->id,
                    [
                        'received_date' => now()->toDateString(),
                        'created_by' => $supplier->id,
                        'updated_by' => $supplier->id,
                    ],
                    [[
                        'product_id' => $product->id,
                        'product_variant_id' => $variant->id,
                        'received_quantity' => $variantStock,
                        'damaged_quantity' => 0,
                        'unit_cost' => (string) ($definition['cost'] ?? $cost),
                    ]]
                );
            }
        }

        return $product->fresh(['variants', 'supplier.company', 'market.currency', 'descriptions']);
    }
}
