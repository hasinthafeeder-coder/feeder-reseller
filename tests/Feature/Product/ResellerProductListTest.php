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
use Feeder\Core\Support\ResellerProductPricing;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class ResellerProductListTest extends TestCase
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

    public function test_no_filters_shows_all_products_from_assigned_suppliers(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');

        $supplierA = $this->makeSupplierUser('Supplier A');
        $supplierB = $this->makeSupplierUser('Supplier B');
        $unassignedSupplier = $this->makeSupplierUser('Supplier C');

        $this->assignSupplier($reseller, $supplierA);
        $this->assignSupplier($reseller, $supplierB);

        $this->makeProduct($supplierA, $category, 'Product A1');
        $this->makeProduct($supplierB, $category, 'Product B1');
        $this->makeProduct($unassignedSupplier, $category, 'Product Hidden');

        $this->actingAs($reseller)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Product A1')
            ->assertSee('Product B1')
            ->assertDontSee('Product Hidden');
    }

    public function test_supplier_filter_limits_products_to_selected_supplier(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');

        $supplierA = $this->makeSupplierUser('Supplier A');
        $supplierB = $this->makeSupplierUser('Supplier B');

        $this->assignSupplier($reseller, $supplierA);
        $this->assignSupplier($reseller, $supplierB);

        $this->makeProduct($supplierA, $category, 'Product A1');
        $this->makeProduct($supplierB, $category, 'Product B1');

        $this->actingAs($reseller)
            ->get(route('products.index', ['suppliers' => [$supplierA->id]]))
            ->assertOk()
            ->assertSee('Product A1')
            ->assertDontSee('Product B1');
    }

    public function test_category_filter_limits_products(): void
    {
        $reseller = $this->makeResellerUser();
        $electronics = $this->makeCategory('Electronics');
        $sports = $this->makeCategory('Sports');
        $supplier = $this->makeSupplierUser('Supplier A');

        $this->assignSupplier($reseller, $supplier);
        $this->makeProduct($supplier, $electronics, 'Electronics Item');
        $this->makeProduct($supplier, $sports, 'Sports Item');

        $this->actingAs($reseller)
            ->get(route('products.index', ['categories' => [$electronics->id]]))
            ->assertOk()
            ->assertSee('Electronics Item')
            ->assertDontSee('Sports Item');
    }

    public function test_category_filter_displays_hierarchical_structure(): void
    {
        $reseller = $this->makeResellerUser();
        $electronics = $this->makeCategory('Electronics');
        $audio = $this->makeCategory('Audio', $electronics);
        $earphones = $this->makeCategory('Earphones', $audio);
        $fashion = $this->makeCategory('Fashion');
        $supplier = $this->makeSupplierUser('Supplier A');

        $this->assignSupplier($reseller, $supplier);
        $this->makeProduct($supplier, $earphones, 'Nested Category Product');
        $this->makeProduct($supplier, $fashion, 'Unrelated Fashion Product');

        $this->actingAs($reseller)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Nested Category Product')
            ->assertSee('Unrelated Fashion Product')
            ->assertSee('filter-category-group-'.$electronics->id, false)
            ->assertSee('filter-category-'.$earphones->id, false)
            ->assertSee('filter-category-'.$fashion->id, false)
            ->assertSee('Earphones')
            ->assertSee('Audio')
            ->assertSee('Fashion');
    }

    public function test_category_filter_excludes_unrelated_categories_without_products(): void
    {
        $reseller = $this->makeResellerUser();
        $electronics = $this->makeCategory('Electronics');
        $this->makeCategory('Audio', $electronics);
        $home = $this->makeCategory('Home & Kitchen');
        $storage = $this->makeCategory('Storage', $home);
        $supplier = $this->makeSupplierUser('Supplier A');

        $this->assignSupplier($reseller, $supplier);
        $this->makeProduct($supplier, $storage, 'Storage Product');

        $this->actingAs($reseller)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Storage Product')
            ->assertSee('filter-category-group-'.$home->id, false)
            ->assertSee('filter-category-'.$storage->id, false)
            ->assertDontSee('filter-category-group-'.$electronics->id, false);
    }

    public function test_supplier_type_pro_filter(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');

        $proSupplier = $this->makeSupplierUser('Pro Supplier', SupplierType::PRO);
        $standardSupplier = $this->makeSupplierUser('Standard Supplier', SupplierType::STANDARD);

        $this->assignSupplier($reseller, $proSupplier);
        $this->assignSupplier($reseller, $standardSupplier);

        $this->makeProduct($proSupplier, $category, 'Pro Product');
        $this->makeProduct($standardSupplier, $category, 'Standard Product');

        $this->actingAs($reseller)
            ->get(route('products.index', ['supplier_types' => [SupplierType::PRO->value]]))
            ->assertOk()
            ->assertSee('Pro Product')
            ->assertDontSee('Standard Product');
    }

    public function test_supplier_type_standard_filter(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');

        $proSupplier = $this->makeSupplierUser('Pro Supplier', SupplierType::PRO);
        $standardSupplier = $this->makeSupplierUser('Standard Supplier', SupplierType::STANDARD);

        $this->assignSupplier($reseller, $proSupplier);
        $this->assignSupplier($reseller, $standardSupplier);

        $this->makeProduct($proSupplier, $category, 'Pro Product');
        $this->makeProduct($standardSupplier, $category, 'Standard Product');

        $this->actingAs($reseller)
            ->get(route('products.index', ['supplier_types' => [SupplierType::STANDARD->value]]))
            ->assertOk()
            ->assertSee('Standard Product')
            ->assertDontSee('Pro Product');
    }

    public function test_combined_filters_apply_all_conditions(): void
    {
        $reseller = $this->makeResellerUser();
        $electronics = $this->makeCategory('Electronics');
        $sports = $this->makeCategory('Sports');

        $supplier = $this->makeSupplierUser('Pro Supplier', SupplierType::PRO);
        $otherSupplier = $this->makeSupplierUser('Other Supplier', SupplierType::PRO);

        $this->assignSupplier($reseller, $supplier);
        $this->assignSupplier($reseller, $otherSupplier);

        $this->makeProduct($supplier, $electronics, 'Match Product');
        $this->makeProduct($supplier, $sports, 'Wrong Category');
        $this->makeProduct($otherSupplier, $electronics, 'Wrong Supplier');

        $this->actingAs($reseller)
            ->get(route('products.index', [
                'suppliers' => [$supplier->id],
                'categories' => [$electronics->id],
                'supplier_types' => [SupplierType::PRO->value],
            ]))
            ->assertOk()
            ->assertSee('Match Product')
            ->assertDontSee('Wrong Category')
            ->assertDontSee('Wrong Supplier');
    }

    public function test_unassigned_supplier_filter_is_ignored_securely(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');

        $assignedSupplier = $this->makeSupplierUser('Assigned Supplier');
        $foreignSupplier = $this->makeSupplierUser('Foreign Supplier');

        $this->assignSupplier($reseller, $assignedSupplier);
        $this->makeProduct($assignedSupplier, $category, 'Visible Product');
        $this->makeProduct($foreignSupplier, $category, 'Foreign Product');

        $this->actingAs($reseller)
            ->get(route('products.index', ['suppliers' => [$foreignSupplier->id]]))
            ->assertOk()
            ->assertDontSee('Foreign Product')
            ->assertDontSee('Visible Product');
    }

    public function test_clear_filters_route_shows_full_catalog(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('Supplier A');

        $this->assignSupplier($reseller, $supplier);
        $this->makeProduct($supplier, $category, 'Catalog Product');

        $this->actingAs($reseller)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Catalog Product')
            ->assertSee('Clear Filters');
    }

    public function test_zero_stock_products_remain_visible_with_out_of_stock_status(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('Supplier A');

        $this->assignSupplier($reseller, $supplier);
        $this->makeProduct($supplier, $category, 'Zero Stock Product', stockQuantity: 0);

        $this->actingAs($reseller)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Zero Stock Product')
            ->assertSee('Out of Stock');
    }

    public function test_pro_supplier_badge_is_displayed(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('Pro Supplier', SupplierType::PRO);

        $this->assignSupplier($reseller, $supplier);
        $this->makeProduct($supplier, $category, 'Pro Badge Product');

        $this->actingAs($reseller)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Pro Badge Product')
            ->assertSee('>PRO</span>', false);
    }

    public function test_standard_supplier_does_not_show_pro_badge(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('Standard Supplier', SupplierType::STANDARD);

        $this->assignSupplier($reseller, $supplier);
        $this->makeProduct($supplier, $category, 'Standard Badge Product');

        $response = $this->actingAs($reseller)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Standard Badge Product');

        $this->assertSame(0, substr_count($response->getContent(), '>PRO</span>'));
    }

    public function test_price_lock_on_displays_selling_price_cost_and_commission(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('Supplier A');

        $this->assignSupplier($reseller, $supplier);
        $this->makeProduct(
            $supplier,
            $category,
            'Locked Price Product',
            priceLocked: true,
            cost: 1000,
            companyCommission: 150,
            sellingPrice: 2500,
        );

        $this->actingAs($reseller)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Locked Price Product')
            ->assertSee('Selling Price')
            ->assertSee('LKR 2,500.00')
            ->assertSee('Cost LKR 1,150.00')
            ->assertSee('Commission:')
            ->assertSee('LKR 1,350.00')
            ->assertDontSee('Suggested Price');
    }

    public function test_price_lock_off_displays_suggested_range_cost_and_commission_range(): void
    {
        $reseller = $this->makeResellerUser();
        $category = $this->makeCategory('Electronics');
        $supplier = $this->makeSupplierUser('Supplier A');

        $this->assignSupplier($reseller, $supplier);
        $this->makeProduct(
            $supplier,
            $category,
            'Unlocked Price Product',
            priceLocked: false,
            cost: 1000,
            companyCommission: 150,
            suggestedPriceMin: 2500,
            suggestedPriceMax: 3500,
        );

        $this->actingAs($reseller)
            ->get(route('products.index'))
            ->assertOk()
            ->assertSee('Unlocked Price Product')
            ->assertSee('Suggested Price')
            ->assertSee('LKR 2,500.00')
            ->assertSee('LKR 3,500.00')
            ->assertSee('Cost LKR 1,150.00')
            ->assertSee('Commission:')
            ->assertSee('LKR 1,350.00')
            ->assertSee('LKR 2,350.00')
            ->assertDontSee('Selling Price');
    }

    public function test_reseller_product_pricing_helpers(): void
    {
        $variant = new ProductVariant([
            'cost' => 1000,
            'company_commission' => 150,
            'selling_price' => 2500,
            'suggested_price_min' => 2500,
            'suggested_price_max' => 3500,
        ]);

        $this->assertSame(1150.0, ResellerProductPricing::resellerCost($variant));

        $lockedCommission = ResellerProductPricing::commissionRange($variant, true);
        $this->assertSame(1350.0, $lockedCommission['min']);
        $this->assertSame(1350.0, $lockedCommission['max']);

        $unlockedCommission = ResellerProductPricing::commissionRange($variant, false);
        $this->assertSame(1350.0, $unlockedCommission['min']);
        $this->assertSame(2350.0, $unlockedCommission['max']);
    }

    private function makeResellerUser(): User
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
        SupplierType $supplierType = SupplierType::STANDARD
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
        $this->configureSupplierCompany($company, 'lk');

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
    ): Product {
        $product = Product::query()->create([
            'uuid' => (string) Str::uuid(),
            'supplier_id' => $supplier->id,
            'category_id' => $category->id,
            'market_id' => $this->marketByCode('lk')->id,
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

        ProductDescription::query()->create([
            'product_id' => $product->id,
            'language_code' => 'en',
            'description' => $productName.' description',
        ]);

        $variant = ProductVariant::query()->create([
            'uuid' => (string) Str::uuid(),
            'product_id' => $product->id,
            'name' => 'Default',
            'barcode' => 'BC-'.strtoupper(Str::random(8)),
            'cost' => $cost,
            'selling_price' => $sellingPrice,
            'suggested_price_min' => $suggestedPriceMin,
            'suggested_price_max' => $suggestedPriceMax,
            'weight' => 0.500,
            'company_commission' => $companyCommission,
            'sort_order' => 0,
            'is_active' => true,
            'created_by' => $supplier->id,
            'updated_by' => $supplier->id,
        ]);

        if ($stockQuantity > 0) {
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
                    'received_quantity' => $stockQuantity,
                    'damaged_quantity' => 0,
                    'unit_cost' => (string) $cost,
                ]]
            );
        }

        return $product->fresh(['variants', 'supplier.company', 'market.currency']);
    }
}
