<?php

namespace Tests\Feature\Order;

use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\OrderImportBatchStatus;
use Feeder\Core\Enums\OrderImportRowStatus;
use Feeder\Core\Enums\OrderStatus;
use Feeder\Core\Enums\OrderType;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\ProductStatus;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Order;
use Feeder\Core\Models\OrderImportBatch;
use Feeder\Core\Models\OrderImportRow;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Product;
use Feeder\Core\Models\ProductCategory;
use Feeder\Core\Models\ProductVariant;
use Feeder\Core\Models\ResellerSupplierAssignment;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Order\CustomerBanService;
use Feeder\Core\Services\Order\CustomerIdentityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;
use ZipArchive;

class ResellerOrderImportTest extends TestCase
{
    use SetsUpMarketData;
    use UsesMysqlTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedMarketLookups();
        config(['cache.default' => 'array']);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_template_download_returns_exact_headers(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create']);

        $response = $this->actingAs($reseller)
            ->get(route('orders.import.template'))
            ->assertOk();

        $this->assertSame(
            "name,address,tp_1,tp_2,price,qty,item_code,delivery\n",
            $response->streamedContent()
        );
    }

    public function test_valid_csv_upload_validates_and_process_creates_pending_orders(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create', 'orders.view']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier, [
            'selling_price' => 2500.00,
            'cost' => 1000.00,
            'company_commission' => 150.00,
        ]);

        $phone = '070'.random_int(1000000, 9999999);
        $csv = $this->csvContent([
            ['Import Customer', '12 Temple Road', $phone, '', '2500', '2', (string) $variant->id, '350'],
        ]);

        $upload = $this->actingAs($reseller)->postJson(route('orders.import.upload'), [
            'file' => $this->csvUpload($csv),
        ]);

        $upload->assertOk();
        $batchUuid = $upload->json('data.batch.uuid');
        $this->assertNotEmpty($batchUuid);
        $this->assertSame(1, $upload->json('data.summary.valid_rows'));
        $this->assertSame(0, $upload->json('data.summary.invalid_rows'));

        $process = $this->actingAs($reseller)->postJson(route('orders.import.process', $batchUuid));
        $process->assertOk();
        $this->assertSame(1, $process->json('data.summary.created_rows'));
        $this->assertSame(0, $process->json('data.summary.valid_rows'));

        $order = Order::query()->where('primary_phone_snapshot', $phone)->first();
        $this->assertNotNull($order);
        $this->assertSame(OrderStatus::PENDING, $order->status);
        $this->assertSame(OrderType::NEW, $order->order_type);
        $this->assertNull($order->draft_courier_id);
        $this->assertNull($order->shipment);
        $this->assertSame('2500.00', (string) $order->items->first()->unit_selling_price);
        $this->assertSame(2, (int) $order->items->first()->quantity);
        $this->assertSame('5000.00', (string) $order->items_subtotal);
        $this->assertSame('350.00', (string) $order->courier_fee_amount);
        $this->assertSame('5350.00', (string) $order->customer_payable_amount);

        $createdRow = collect($process->json('data.rows'))->firstWhere('state', 'created');
        $this->assertNotNull($createdRow);
        $this->assertSame($order->order_number, $createdRow['order_number']);
        $this->assertSame('Import Customer', $createdRow['name']);
        $this->assertSame($phone, $createdRow['tp_1']);
        $this->assertSame('', $createdRow['tp_2']);
        $this->assertSame($variant->product->name.' / '.$variant->name, $createdRow['item_name']);
        $this->assertSame(2, $createdRow['item_qty']);
        $this->assertSame('5000.00', $createdRow['item_amount']);
        $this->assertSame($supplier->company->name, $createdRow['supplier_name']);
        $this->assertIsInt($createdRow['existing_stock']);
        $this->assertGreaterThanOrEqual(0, $createdRow['existing_stock']);
    }

    public function test_valid_xlsx_upload_is_accepted(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier, ['selling_price' => 250.00]);

        $phone = '070'.random_int(1000000, 9999999);
        $file = $this->xlsxUpload([
            ['name', 'address', 'tp_1', 'tp_2', 'price', 'qty', 'item_code', 'delivery'],
            ['Xlsx Customer', '45 Galle Road', $phone, '', '250', '1', (string) $variant->id, '0'],
        ]);

        $this->actingAs($reseller)
            ->postJson(route('orders.import.upload'), ['file' => $file])
            ->assertOk()
            ->assertJsonPath('data.summary.valid_rows', 1)
            ->assertJsonPath('data.batch.file_type', 'xlsx');
    }

    public function test_invalid_file_and_malformed_headers_and_empty_file_are_rejected(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create']);

        $this->actingAs($reseller)
            ->postJson(route('orders.import.upload'), [
                'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            ])
            ->assertStatus(422);

        $this->actingAs($reseller)
            ->postJson(route('orders.import.upload'), [
                'file' => $this->csvUpload("foo,bar\n1,2\n"),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->actingAs($reseller)
            ->postJson(route('orders.import.upload'), [
                'file' => $this->csvUpload(''),
            ])
            ->assertStatus(422);
    }

    public function test_row_validation_categories(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $locked = $this->makeVariant($supplier, [
            'selling_price' => 2500.00,
        ], 'lk', true);
        $unlocked = $this->makeVariant($supplier, [
            'selling_price' => 2500.00,
            'suggested_price_min' => 2000.00,
            'suggested_price_max' => 3000.00,
        ], 'lk', false);

        $otherSupplier = $this->makeSupplierUser();
        $inaccessible = $this->makeVariant($otherSupplier, ['selling_price' => 100.00]);

        $csv = $this->csvContent([
            ['', 'Addr', '0701111111', '', '2500', '1', (string) $locked->id, '0'], // missing name
            ['Name', 'Addr', '', '', '2500', '1', (string) $locked->id, '0'], // missing phone
            ['Name', 'Addr', '0701111112', '', '2500', '0', (string) $locked->id, '0'], // invalid qty
            ['Name', 'Addr', '0701111113', '', '2500', '1', 'abc', '0'], // invalid item code
            ['Name', 'Addr', '0701111114', '', '2500', '1', '99999999', '0'], // nonexistent
            ['Name', 'Addr', '0701111115', '', '100', '1', (string) $inaccessible->id, '0'], // inaccessible
            ['Name', 'Addr', '0701111116', '', '1111', '1', (string) $locked->id, '0'], // locked mismatch
            ['Name', 'Addr', '0701111117', '', '1500', '1', (string) $unlocked->id, '0'], // below min
            ['Name', 'Addr', '0701111118', '', '3500', '1', (string) $unlocked->id, '0'], // above max
            ['Name', 'Addr', '0701111119', '', '2500', '1', (string) $unlocked->id, '0'], // valid unlocked
            ['Name', 'Addr', '0701111120', '', '2500', '1', (string) $locked->id, '-10'], // invalid delivery
            ['Name', 'Addr', '0701111121', '', '2500', '1', (string) $locked->id, ''], // valid locked empty delivery
        ]);

        $response = $this->actingAs($reseller)->postJson(route('orders.import.upload'), [
            'file' => $this->csvUpload($csv),
        ])->assertOk();

        $rows = collect($response->json('data.rows'));
        $this->assertSame('invalid', $rows[0]['state']);
        $this->assertSame('invalid', $rows[1]['state']);
        $this->assertSame('invalid', $rows[2]['state']);
        $this->assertSame('invalid', $rows[3]['state']);
        $this->assertSame('invalid', $rows[4]['state']);
        $this->assertSame('invalid', $rows[5]['state']);
        $this->assertSame('invalid', $rows[6]['state']);
        $this->assertSame('invalid', $rows[7]['state']);
        $this->assertSame('invalid', $rows[8]['state']);
        $this->assertSame('valid', $rows[9]['state']);
        $this->assertSame('invalid', $rows[10]['state']);
        $this->assertSame('valid', $rows[11]['state']);
        $this->assertSame(2, $response->json('data.summary.valid_rows'));
    }

    public function test_ban_checks_for_primary_secondary_and_both(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier, ['selling_price' => 250.00]);
        $countryId = (int) $this->marketByCode('lk')->country_id;

        $bannedPrimary = '070'.random_int(1000000, 9999999);
        $bannedSecondary = '071'.random_int(1000000, 9999999);
        $clean = '072'.random_int(1000000, 9999999);

        $identity = app(CustomerIdentityService::class);
        $banService = app(CustomerBanService::class);

        $primaryCustomer = $identity->resolveOrCreate([
            'display_name' => 'Banned Primary',
            'primary_country_id' => $countryId,
            'primary_phone' => $bannedPrimary,
            'primary_phone_country_id' => $countryId,
            'created_by' => $reseller->id,
        ])['customer'];
        $banService->ban($primaryCustomer, (int) $reseller->id, (int) $reseller->company_id, 'test');

        $secondaryCustomer = $identity->resolveOrCreate([
            'display_name' => 'Banned Secondary',
            'primary_country_id' => $countryId,
            'primary_phone' => $bannedSecondary,
            'primary_phone_country_id' => $countryId,
            'created_by' => $reseller->id,
        ])['customer'];
        $banService->ban($secondaryCustomer, (int) $reseller->id, (int) $reseller->company_id, 'test');

        $csv = $this->csvContent([
            ['A', 'Addr', $bannedPrimary, '', '250', '1', (string) $variant->id, '0'],
            ['B', 'Addr', $clean, $bannedSecondary, '250', '1', (string) $variant->id, '0'],
            ['C', 'Addr', $bannedPrimary, $bannedSecondary, '250', '1', (string) $variant->id, '0'],
            ['D', 'Addr', $clean, '', '250', '1', (string) $variant->id, '0'],
        ]);

        $response = $this->actingAs($reseller)->postJson(route('orders.import.upload'), [
            'file' => $this->csvUpload($csv),
        ])->assertOk();

        $rows = collect($response->json('data.rows'));
        $this->assertSame('banned', $rows[0]['state']);
        $this->assertStringContainsString('Primary phone is banned', $rows[0]['reason']);
        $this->assertSame('banned', $rows[1]['state']);
        $this->assertStringContainsString('Secondary phone is banned', $rows[1]['reason']);
        $this->assertSame('banned', $rows[2]['state']);
        $this->assertStringContainsString('Primary phone is banned', $rows[2]['reason']);
        $this->assertStringContainsString('Secondary phone is banned', $rows[2]['reason']);
        $this->assertSame('valid', $rows[3]['state']);
        $this->assertSame(3, $response->json('data.summary.banned_rows'));
        $this->assertSame(1, $response->json('data.summary.valid_rows'));
    }

    public function test_partial_success_and_duplicate_process_is_idempotent(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.create']);
        $supplier = $this->makeSupplierUser();
        $this->assignSupplier($reseller, $supplier);
        $variant = $this->makeVariant($supplier, ['selling_price' => 250.00]);
        $countryId = (int) $this->marketByCode('lk')->country_id;

        $bannedPhone = '070'.random_int(1000000, 9999999);
        $identity = app(CustomerIdentityService::class);
        $banService = app(CustomerBanService::class);
        $bannedCustomer = $identity->resolveOrCreate([
            'display_name' => 'Banned',
            'primary_country_id' => $countryId,
            'primary_phone' => $bannedPhone,
            'primary_phone_country_id' => $countryId,
            'created_by' => $reseller->id,
        ])['customer'];
        $banService->ban($bannedCustomer, (int) $reseller->id, (int) $reseller->company_id, 'test');

        $rows = [];
        for ($i = 0; $i < 6; $i++) {
            $rows[] = [
                'Valid '.$i,
                'Addr',
                '070'.random_int(1000000, 9999999),
                '',
                '250',
                '1',
                (string) $variant->id,
                '100',
            ];
        }
        $rows[] = ['Banned 1', 'Addr', $bannedPhone, '', '250', '1', (string) $variant->id, '0'];
        $rows[] = ['Banned 2', 'Addr', $bannedPhone, '', '250', '1', (string) $variant->id, '0'];
        $rows[] = ['Invalid 1', 'Addr', '070'.random_int(1000000, 9999999), '', '999', '1', (string) $variant->id, '0'];
        $rows[] = ['Invalid 2', '', '070'.random_int(1000000, 9999999), '', '250', '1', (string) $variant->id, '0'];

        $upload = $this->actingAs($reseller)->postJson(route('orders.import.upload'), [
            'file' => $this->csvUpload($this->csvContent($rows)),
        ])->assertOk();

        $this->assertSame(10, $upload->json('data.summary.total_rows'));
        $this->assertSame(6, $upload->json('data.summary.valid_rows'));
        $this->assertSame(2, $upload->json('data.summary.banned_rows'));
        $this->assertSame(2, $upload->json('data.summary.invalid_rows'));

        $batchUuid = $upload->json('data.batch.uuid');
        $before = Order::query()->count();

        $first = $this->actingAs($reseller)->postJson(route('orders.import.process', $batchUuid))->assertOk();
        $this->assertSame(6, $first->json('data.summary.created_rows'));
        $this->assertSame(2, $first->json('data.summary.banned_rows'));
        $this->assertSame(2, $first->json('data.summary.invalid_rows'));
        $this->assertSame($before + 6, Order::query()->count());

        $second = $this->actingAs($reseller)->postJson(route('orders.import.process', $batchUuid))->assertOk();
        $this->assertSame(6, $second->json('data.summary.created_rows'));
        $this->assertSame($before + 6, Order::query()->count());

        $batch = OrderImportBatch::query()->where('uuid', $batchUuid)->first();
        $this->assertSame(OrderImportBatchStatus::COMPLETED, $batch->status);
        $this->assertSame(6, OrderImportRow::query()->where('order_import_batch_id', $batch->id)->where('status', OrderImportRowStatus::CREATED)->count());
        $this->assertSame(2, OrderImportRow::query()->where('order_import_batch_id', $batch->id)->where('status', OrderImportRowStatus::BANNED)->count());
        $this->assertSame(2, OrderImportRow::query()->where('order_import_batch_id', $batch->id)->where('status', OrderImportRowStatus::INVALID)->count());
    }

    public function test_import_requires_orders_create_permission(): void
    {
        $reseller = $this->makeResellerWithPermission(['orders.view']);

        $this->actingAs($reseller)
            ->postJson(route('orders.import.upload'), [
                'file' => $this->csvUpload($this->csvContent([
                    ['A', 'B', '0701111111', '', '1', '1', '1', '0'],
                ])),
            ])
            ->assertForbidden();
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csvContent(array $rows): string
    {
        $lines = ['name,address,tp_1,tp_2,price,qty,item_code,delivery'];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(
                static fn ($value) => str_contains((string) $value, ',')
                    ? '"'.str_replace('"', '""', (string) $value).'"'
                    : (string) $value,
                $row
            ));
        }

        return implode("\n", $lines)."\n";
    }

    private function csvUpload(string $contents): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('orders.csv', $contents);
    }

    /**
     * @param  list<list<string>>  $matrix
     */
    private function xlsxUpload(array $matrix): UploadedFile
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);

        $shared = [];
        $sharedIndex = [];
        foreach ($matrix as $row) {
            foreach ($row as $value) {
                $value = (string) $value;
                if (! array_key_exists($value, $sharedIndex)) {
                    $sharedIndex[$value] = count($shared);
                    $shared[] = $value;
                }
            }
        }

        $sharedXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="'
            .count($shared).'" uniqueCount="'.count($shared).'">';
        foreach ($shared as $value) {
            $sharedXml .= '<si><t>'.htmlspecialchars($value, ENT_XML1).'</t></si>';
        }
        $sharedXml .= '</sst>';

        $sheetRows = '';
        foreach ($matrix as $r => $row) {
            $rowNumber = $r + 1;
            $sheetRows .= '<row r="'.$rowNumber.'">';
            foreach ($row as $c => $value) {
                $col = $this->xlsxColumn($c).$rowNumber;
                $idx = $sharedIndex[(string) $value];
                $sheetRows .= '<c r="'.$col.'" t="s"><v>'.$idx.'</v></c>';
            }
            $sheetRows .= '</row>';
        }

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$sheetRows.'</sheetData></worksheet>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            .'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->addFromString('xl/sharedStrings.xml', $sharedXml);
        $zip->close();

        $binary = file_get_contents($tmp);
        @unlink($tmp);

        return UploadedFile::fake()->createWithContent('orders.xlsx', $binary ?: '');
    }

    private function xlsxColumn(int $index): string
    {
        $index++;
        $letters = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }

    /**
     * @param  list<string>  $permissionSlugs
     * @param  list<string>  $marketCodes
     */
    private function makeResellerWithPermission(array $permissionSlugs, array $marketCodes = ['lk']): User
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

        $role = Role::query()->create([
            'uuid' => (string) Str::uuid(),
            'portal_id' => $portal->id,
            'company_id' => null,
            'slug' => 'owner-import-'.Str::lower(Str::random(6)),
            'name' => 'Owner Import Test',
            'description' => 'Owner Import Test',
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
    private function makeVariant(
        User $supplier,
        array $variantOverrides = [],
        string $marketCode = 'lk',
        bool $priceLocked = true,
    ): ProductVariant {
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
            'market_id' => $this->marketByCode($marketCode)->id,
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
