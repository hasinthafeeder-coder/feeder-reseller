<?php

namespace Tests\Feature\Notification;

use App\Services\FileServer\FileProxyService;
use Feeder\Core\Authorization\Menu\ResellerMenu;
use Feeder\Core\Authorization\Services\MenuService;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\FileCategory;
use Feeder\Core\Enums\NotificationAudienceType;
use Feeder\Core\Enums\NotificationDeliveryChannel;
use Feeder\Core\Enums\NotificationDeliveryStatus;
use Feeder\Core\Enums\NotificationType;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\File;
use Feeder\Core\Models\Notification;
use Feeder\Core\Models\NotificationDelivery;
use Feeder\Core\Models\NotificationRecipient;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\User;
use Feeder\Core\Services\Notification\NotificationInboxService;
use Feeder\Core\Services\UuidService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class ResellerNotificationCenterTest extends TestCase
{
    use SetsUpMarketData;
    use UsesMysqlTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedMarketLookups();
        $this->assertNotificationSchemaReady();
        Carbon::setTestNow('2026-11-10 12:00:00');
        config(['cache.default' => 'array']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_routes_and_menu_expose_reseller_notification_center(): void
    {
        $this->assertTrue(Route::has('notifications.index'));
        $this->assertTrue(Route::has('notifications.show'));
        $this->assertTrue(Route::has('notifications.read'));
        $this->assertTrue(Route::has('notifications.live'));

        $item = null;
        foreach (ResellerMenu::build()->getSections() as $section) {
            foreach ($section->getItems() as $candidate) {
                if ($candidate->getTitle() === 'Notifications') {
                    $item = $candidate;
                }
            }
        }

        $this->assertNotNull($item);
        $this->assertSame('notifications.index', $item->getRoute());
        $this->assertSame('notifications.view', $item->getPermission());

        $reseller = $this->makeResellerWithPermission(['dashboard.view']);
        $this->assertNotContains('Notifications', $this->menuTitles(app(MenuService::class)->getForUser($reseller)));

        $withView = $this->makeResellerWithPermission(['notifications.view']);
        $this->assertContains('Notifications', $this->menuTitles(app(MenuService::class)->getForUser($withView)));
    }

    public function test_recipient_isolation_and_detail_authorization(): void
    {
        $owner = $this->makeResellerWithPermission(['notifications.view']);
        $stranger = $this->makeResellerWithPermission(['notifications.view']);
        $notification = $this->seedAddressedNotification($owner, [
            'title' => 'Owner only offer',
            'body' => 'Visible to owner company only',
        ]);

        $this->get(route('notifications.index'))->assertRedirect(route('login', absolute: false));

        $this->actingAs($owner)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Owner only offer')
            ->assertSee('From Admin', false)
            ->assertSee('Unread');

        $this->actingAs($owner)
            ->get(route('notifications.show', $notification))
            ->assertOk()
            ->assertSee('Owner only offer')
            ->assertSee('From Admin', false)
            ->assertSee('Visible to owner company only');

        $this->actingAs($stranger)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertDontSee('Owner only offer');

        $this->actingAs($stranger)
            ->get(route('notifications.show', $notification))
            ->assertForbidden();

        $this->actingAs($stranger)
            ->post(route('notifications.read', $notification))
            ->assertForbidden();
    }

    public function test_supplier_sender_label_uses_publisher_company_name(): void
    {
        $reseller = $this->makeResellerWithPermission(['notifications.view']);
        $supplierCompany = Company::query()->create([
            'uuid' => UuidService::generate(),
            'portal_id' => Portal::query()->firstOrCreate(
                ['code' => PortalCode::SUPPLIER->value],
                [
                    'uuid' => UuidService::generate(),
                    'name' => 'Supplier Portal',
                    'subdomain' => 'supplier-'.Str::lower(Str::random(4)),
                    'description' => 'Supplier Portal',
                    'is_active' => true,
                ]
            )->id,
            'name' => 'Acme Supplies Co',
            'email' => 'acme-'.Str::uuid().'@feeder.local',
            'phone' => '077'.random_int(100000, 999999),
            'registration_number' => 'REG-'.Str::random(6),
            'status' => CompanyStatus::ACTIVE->value,
        ]);

        $notification = $this->seedAddressedNotification($reseller, [
            'title' => 'Supplier restock notice',
            'body' => 'Back in stock',
            'type' => NotificationType::RESTOCK,
        ]);
        $notification->forceFill([
            'publisher_company_id' => $supplierCompany->id,
            'source_event_type' => 'supplier.assigned_reseller_notification.published',
        ])->save();

        $this->assertSame('Acme Supplies Co', $notification->fresh()->senderLabel());

        $this->actingAs($reseller)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('From Acme Supplies Co', false);

        $this->actingAs($reseller)
            ->get(route('notifications.show', $notification))
            ->assertOk()
            ->assertSee('From Acme Supplies Co', false)
            ->assertSee('Acme Supplies Co', false);

        $live = $this->actingAs($reseller)->getJson(route('notifications.live'));
        $live->assertOk();
        $live->assertJsonFragment([
            'title' => 'Supplier restock notice',
            'sender_label' => 'Acme Supplies Co',
        ]);
    }

    public function test_unread_count_and_opening_detail_marks_read_and_opened(): void
    {
        $owner = $this->makeResellerWithPermission(['notifications.view']);
        $stranger = $this->makeResellerWithPermission(['notifications.view']);
        $notification = $this->seedAddressedNotification($owner, [
            'title' => 'Mark me read',
            'body' => 'Read status body',
        ]);
        $recipient = NotificationRecipient::query()
            ->where('notification_id', $notification->id)
            ->where('recipient_user_id', $owner->id)
            ->firstOrFail();

        $inbox = app(NotificationInboxService::class);
        $this->assertSame(1, $inbox->unreadCountFor($owner));
        $this->assertSame(0, $inbox->unreadCountFor($stranger));

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('headerNotificationsDropdown', false)
            ->assertSee((string) $notification->title);

        $this->actingAs($owner)
            ->get(route('notifications.show', $notification))
            ->assertOk()
            ->assertSee('Mark me read')
            ->assertSee('Read');

        $fresh = $recipient->fresh();
        $this->assertNotNull($fresh->read_at);
        $this->assertNotNull($fresh->opened_at);
        $this->assertSame(0, $inbox->unreadCountFor($owner));

        $this->actingAs($stranger)
            ->post(route('notifications.read', $notification))
            ->assertForbidden();

        $this->assertNotNull($recipient->fresh()->read_at);
    }

    public function test_publication_and_expiry_filtering_hides_unavailable_notifications(): void
    {
        $reseller = $this->makeResellerWithPermission(['notifications.view']);

        $scheduled = $this->seedAddressedNotification($reseller, [
            'title' => 'Scheduled notice',
            'body' => 'Not yet',
            'publish_at' => '2026-11-10 15:00:00',
            'expires_at' => '2026-11-10 20:00:00',
        ]);
        $active = $this->seedAddressedNotification($reseller, [
            'title' => 'Active notice',
            'body' => 'Visible now',
            'publish_at' => '2026-11-10 10:00:00',
            'expires_at' => '2026-11-10 18:00:00',
        ]);
        $expired = $this->seedAddressedNotification($reseller, [
            'title' => 'Expired notice',
            'body' => 'Too late',
            'publish_at' => '2026-11-10 08:00:00',
            'expires_at' => '2026-11-10 11:00:00',
        ]);

        $this->actingAs($reseller)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Active notice')
            ->assertDontSee('Scheduled notice')
            ->assertDontSee('Expired notice');

        $this->actingAs($reseller)
            ->get(route('notifications.show', $scheduled))
            ->assertForbidden();
        $this->actingAs($reseller)
            ->get(route('notifications.show', $expired))
            ->assertForbidden();
        $this->actingAs($reseller)
            ->get(route('notifications.show', $active))
            ->assertOk();
    }

    public function test_dashboard_shows_valid_banners_and_skips_text_only_or_missing_banners(): void
    {
        $reseller = $this->makeResellerWithPermission(['notifications.view', 'dashboard.view']);
        $validBanner = $this->makeBannerFile();
        $inactiveBanner = $this->makeBannerFile(['status' => 'INACTIVE']);

        $withBanner = $this->seedAddressedNotification($reseller, [
            'title' => 'Bannered restock',
            'body' => 'Has image',
            'banner_file_uuid' => $validBanner->uuid,
            'type' => NotificationType::RESTOCK,
        ]);
        $textOnly = $this->seedAddressedNotification($reseller, [
            'title' => 'Text only offer',
            'body' => 'No banner image',
            'banner_file_uuid' => null,
        ]);
        $inactiveBannerNotice = $this->seedAddressedNotification($reseller, [
            'title' => 'Inactive banner offer',
            'body' => 'Banner file is inactive',
            'banner_file_uuid' => $inactiveBanner->uuid,
        ]);

        $response = $this->actingAs($reseller)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('id="resellerNotificationBannerCarousel"', false);
        $response->assertSee(route('files.view', $validBanner->uuid), false);
        $response->assertSee(route('notifications.show', $withBanner), false);
        // Header dropdown lists recent inbox items, including text-only notices.
        $response->assertSee('Text only offer');
        $response->assertSee('headerNotificationsDropdown', false);

        $content = (string) $response->getContent();
        $carouselStart = strpos($content, 'id="resellerNotificationBannerCarousel"');
        $this->assertNotFalse($carouselStart);
        $carouselChunk = substr($content, $carouselStart, 3000);
        $this->assertStringContainsString(route('files.view', $validBanner->uuid), $carouselChunk);
        $this->assertStringNotContainsString('Text only offer', $carouselChunk);
        $this->assertStringNotContainsString(route('files.view', $inactiveBanner->uuid), $carouselChunk);
        $this->assertStringNotContainsString((string) $inactiveBannerNotice->uuid, $carouselChunk);
    }

    public function test_live_poll_returns_scoped_unread_recent_and_banners_without_mutating_read_state(): void
    {
        $owner = $this->makeResellerWithPermission(['notifications.view', 'meetings.view', 'dashboard.view']);
        $stranger = $this->makeResellerWithPermission(['notifications.view', 'meetings.view', 'dashboard.view']);
        $denied = $this->makeResellerWithPermission(['dashboard.view']);
        $banner = $this->makeBannerFile();

        $notice = $this->seedAddressedNotification($owner, [
            'title' => 'Live poll notice',
            'body' => 'Appears without refresh',
            'banner_file_uuid' => $banner->uuid,
            'type' => NotificationType::MEETING_REMINDER,
        ]);

        $this->get(route('notifications.live'))->assertRedirect(route('login', absolute: false));
        $this->actingAs($denied)->get(route('notifications.live'))->assertForbidden();

        $response = $this->actingAs($owner)
            ->getJson(route('notifications.live', ['include_banners' => 1]))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('unread_meeting_count', 1)
            ->assertJsonPath('poll_interval_ms', 15000)
            ->assertJsonFragment([
                'notification_uuid' => (string) $notice->uuid,
                'title' => 'Live poll notice',
                'is_unread' => true,
            ]);

        $payload = $response->json();
        $this->assertNotEmpty($payload['version']);
        $this->assertSame(1, count(array_filter(
            $payload['items'],
            static fn (array $item): bool => $item['notification_uuid'] === (string) $notice->uuid
        )));
        $this->assertTrue(collect($payload['banners'])->contains(
            fn (array $slide): bool => ($slide['banner_file_uuid'] ?? null) === $banner->uuid
        ));

        $recipient = NotificationRecipient::query()
            ->where('notification_id', $notice->id)
            ->where('recipient_user_id', $owner->id)
            ->firstOrFail();
        $this->assertNull($recipient->fresh()->read_at);
        $this->assertNull($recipient->fresh()->opened_at);

        $this->actingAs($stranger)
            ->getJson(route('notifications.live'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonMissing(['notification_uuid' => (string) $notice->uuid]);

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('notificationLiveConfig', false)
            ->assertSee('notification-live.js', false);
    }

    public function test_banner_file_access_requires_addressed_recipient(): void
    {
        $owner = $this->makeResellerWithPermission(['notifications.view']);
        $stranger = $this->makeResellerWithPermission(['notifications.view']);
        $banner = $this->makeBannerFile();
        $this->seedAddressedNotification($owner, [
            'title' => 'Protected banner',
            'body' => 'Banner ACL',
            'banner_file_uuid' => $banner->uuid,
        ]);

        $proxy = Mockery::mock(FileProxyService::class);
        $proxy->shouldReceive('view')
            ->once()
            ->with($banner->uuid)
            ->andReturn(response('banner-bytes', 200)->header('Content-Type', 'image/jpeg'));
        $this->app->instance(FileProxyService::class, $proxy);

        $this->actingAs($owner)
            ->get(route('files.view', $banner->uuid))
            ->assertOk();

        $this->actingAs($stranger)
            ->get(route('files.view', $banner->uuid))
            ->assertForbidden();
    }

    public function test_menu_unread_badge_reflects_inbox_count(): void
    {
        $reseller = $this->makeResellerWithPermission(['notifications.view']);
        $this->seedAddressedNotification($reseller, [
            'title' => 'Unread badge notice',
            'body' => 'Counts toward badge',
        ]);

        $item = null;
        foreach (app(MenuService::class)->getForUser($reseller)->getSections() as $section) {
            foreach ($section->getItems() as $candidate) {
                if ($candidate->getTitle() === 'Notifications') {
                    $item = $candidate;
                }
            }
        }

        $this->assertNotNull($item);
        $this->assertSame(1, $item->getBadge($reseller));
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
            'name' => 'Reseller Notify Co '.Str::lower(Str::random(4)),
            'email' => 'notify-'.Str::uuid().'@feeder.local',
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
                'slug' => 'notify-owner-'.Str::lower(Str::random(4)),
            ],
            [
                'uuid' => UuidService::generate(),
                'company_id' => null,
                'name' => 'Notify Owner Test',
                'description' => 'Notify Owner Test',
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
                    'module' => $slug === 'notifications.view' ? 'Notifications' : 'Dashboard',
                    'group' => $slug === 'notifications.view' ? 'Inbox' : 'Dashboard',
                    'name' => $slug,
                    'description' => null,
                    'sort_order' => 10,
                ]
            );
            $permissionIds[] = $permission->id;
        }

        $role->permissions()->sync($permissionIds);
        $user->forceFill(['role_id' => $role->id])->save();

        return $user->fresh(['company', 'role.portal']);
    }

    /**
     * @param  array{
     *     title?: string,
     *     body?: string,
     *     type?: NotificationType,
     *     publish_at?: string,
     *     expires_at?: string|null,
     *     banner_file_uuid?: string|null
     * }  $overrides
     */
    private function seedAddressedNotification(User $reseller, array $overrides = []): Notification
    {
        $notification = Notification::query()->create([
            'uuid' => UuidService::generate(),
            'type' => ($overrides['type'] ?? NotificationType::PRODUCT_OFFER)->value,
            'title' => $overrides['title'] ?? 'Offer '.Str::upper(Str::random(4)),
            'body' => $overrides['body'] ?? 'Body',
            'audience_type' => NotificationAudienceType::SELECTED_ELIGIBLE_RESELLERS_FOR_PRODUCT->value,
            'created_by' => $reseller->id,
            // Admin/system notices persist null publisher_company_id; supplier notices set the supplier company.
            'publisher_company_id' => $overrides['publisher_company_id'] ?? null,
            'product_id' => null,
            'banner_file_uuid' => $overrides['banner_file_uuid'] ?? null,
            'publish_at' => $overrides['publish_at'] ?? '2026-11-10 10:00:00',
            'expires_at' => array_key_exists('expires_at', $overrides) ? $overrides['expires_at'] : null,
            'source_event_type' => null,
            'source_event_key' => null,
        ]);

        $recipient = NotificationRecipient::query()->create([
            'uuid' => UuidService::generate(),
            'notification_id' => $notification->id,
            'recipient_user_id' => $reseller->id,
            'recipient_company_id' => $reseller->company_id,
            'read_at' => null,
        ]);

        NotificationDelivery::query()->create([
            'uuid' => UuidService::generate(),
            'notification_recipient_id' => $recipient->id,
            'channel' => NotificationDeliveryChannel::IN_APP->value,
            'status' => NotificationDeliveryStatus::DELIVERED->value,
            'delivered_at' => now(),
            'failure_reason' => null,
        ]);

        return $notification->fresh();
    }

    /**
     * @param  array{status?: string, category?: string}  $overrides
     */
    private function makeBannerFile(array $overrides = []): File
    {
        return File::query()->create([
            'uuid' => UuidService::generate(),
            'application' => 'SUPPLIER',
            'entity_type' => 'NOTIFICATION',
            'entity_uuid' => UuidService::generate(),
            'category' => $overrides['category'] ?? FileCategory::NOTIFICATION_BANNER->value,
            'disk' => 'feeder',
            'path' => 'notification-banners/'.Str::uuid().'.jpg',
            'original_name' => 'banner.jpg',
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'size' => 2048,
            'checksum' => hash('sha256', Str::random(16)),
            'visibility' => 'PRIVATE',
            'status' => $overrides['status'] ?? 'ACTIVE',
        ]);
    }

    /**
     * @return list<string>
     */
    private function menuTitles($menu): array
    {
        $titles = [];

        foreach ($menu->getSections() as $section) {
            foreach ($section->getItems() as $item) {
                $titles[] = $item->getTitle();
            }
        }

        return $titles;
    }

    private function assertNotificationSchemaReady(): void
    {
        $this->assertTrue(
            Schema::hasTable('notifications')
            && Schema::hasTable('notification_recipients')
            && Schema::hasTable('notification_deliveries'),
            'Notification foundation tables must exist for reseller notification center tests.'
        );

        if (! Schema::hasColumn('notification_recipients', 'opened_at')) {
            $path = dirname(base_path())
                .DIRECTORY_SEPARATOR.'dropshipping-db'
                .DIRECTORY_SEPARATOR.'database'
                .DIRECTORY_SEPARATOR.'migrations'
                .DIRECTORY_SEPARATOR.'2026_11_10_160000_add_opened_at_to_notification_recipients_table.php';
            $migration = require $path;
            $migration->up();
        }
    }
}
