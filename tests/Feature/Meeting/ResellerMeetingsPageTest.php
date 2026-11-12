<?php

namespace Tests\Feature\Meeting;

use App\Services\FileServer\FileProxyService;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\FileCategory;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\ResellerMeetingStatus;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\File;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\ResellerMeeting;
use Feeder\Core\Models\ResellerMeetingCheckInToken;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\User;
use Feeder\Core\Services\UuidService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Tests\Support\SetsUpMarketData;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class ResellerMeetingsPageTest extends TestCase
{
    use SetsUpMarketData;
    use UsesMysqlTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedMarketLookups();
        $this->assertMeetingSchemaReady();
        Carbon::setTestNow('2026-11-11 08:00:00');
        config(['cache.default' => 'array']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_active_reseller_without_approved_at_sees_published_meeting_details(): void
    {
        $reseller = $this->makeResellerWithPermission(['meetings.view']);
        $reseller->company->forceFill(['approved_at' => null])->save();

        $meeting = $this->seedMeeting([
            'title' => 'November Townhall',
            'description' => 'Bring your portal login device for QR check-in.',
            'location' => 'Colombo HQ',
            'scheduled_at' => '2037-08-26 12:43:00',
            'status' => ResellerMeetingStatus::SCHEDULED->value,
            'created_by' => $reseller->id,
        ]);

        $response = $this->actingAs($reseller)->get(route('meetings.index'));

        $response->assertOk();
        $response->assertSee('November Townhall', false);
        $response->assertSee('Colombo HQ', false);
        $response->assertSee('Scheduled', false);
        $response->assertSee('View details', false);
        $response->assertDontSee($meeting->uuid.'-foreign-token', false);

        $show = $this->actingAs($reseller)->get(route('meetings.show', $meeting));
        $show->assertOk();
        $show->assertSee('November Townhall', false);
        $show->assertSee('Bring your portal login device for QR check-in.', false);
        $show->assertSee('Colombo HQ', false);
        $show->assertSee('Scheduled', false);
        $show->assertSee('Your check-in QR', false);
        $show->assertSee('View QR', false);
        $show->assertSee('Download QR', false);

        $token = \Feeder\Core\Models\ResellerMeetingCheckInToken::query()
            ->where('meeting_id', $meeting->id)
            ->where('company_id', $reseller->company_id)
            ->whereNull('revoked_at')
            ->first();
        $this->assertNotNull($token);
        $show->assertSee('data-token="'.$token->token.'"', false);

        $download = $this->actingAs($reseller)
            ->get(route('meetings.qr.download', $meeting))
            ->assertOk();
        $download->assertSee($token->token, false);
        $download->assertSee('Unique to ', false);
        $download->assertSee($reseller->company->name, false);
        $download->assertDontSee("centerText('FEEDER'", false);
        $download->assertDontSee('Unique to your reseller account', false);
    }

    public function test_qr_token_is_isolated_to_owning_reseller_company(): void
    {
        $owner = $this->makeResellerWithPermission(['meetings.view']);
        $owner->company->forceFill(['approved_at' => '2026-01-01 00:00:00'])->save();

        $stranger = $this->makeResellerWithPermission(['meetings.view']);
        $stranger->company->forceFill(['approved_at' => null])->save();

        $meeting = $this->seedMeeting([
            'title' => 'QR Isolation Meeting',
            'description' => 'Token isolation check',
            'location' => 'Main branch',
            'scheduled_at' => '2037-09-05 10:00:00',
            'status' => ResellerMeetingStatus::SCHEDULED->value,
            'created_by' => $owner->id,
        ]);

        $ownerToken = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
        $this->seedToken($meeting, $owner->company, $owner, $ownerToken);

        $ownerShow = $this->actingAs($owner)->get(route('meetings.show', $meeting));
        $ownerShow->assertOk();
        $ownerShow->assertSee('Your check-in QR', false);
        $ownerShow->assertSee('data-token="'.$ownerToken.'"', false);
        $ownerShow->assertSee('View QR', false);
        $ownerShow->assertSee('Download QR', false);

        $strangerShow = $this->actingAs($stranger)->get(route('meetings.show', $meeting));
        $strangerShow->assertOk();
        $strangerShow->assertSee('QR Isolation Meeting', false);
        $strangerShow->assertDontSee($ownerToken, false);
        $strangerShow->assertSee('Your check-in QR', false);
        $strangerShow->assertSee('View QR', false);
        $strangerShow->assertSee('Download QR', false);

        $strangerToken = ResellerMeetingCheckInToken::query()
            ->where('meeting_id', $meeting->id)
            ->where('company_id', $stranger->company_id)
            ->whereNull('revoked_at')
            ->value('token');
        $this->assertNotEmpty($strangerToken);
        $this->assertNotSame($ownerToken, $strangerToken);
        $strangerShow->assertSee('data-token="'.$strangerToken.'"', false);

        $this->actingAs($owner)
            ->get(route('meetings.qr.download', $meeting))
            ->assertOk()
            ->assertSee($ownerToken, false)
            ->assertSee($owner->company->name, false)
            ->assertDontSee($strangerToken, false)
            ->assertDontSee($stranger->company->name, false);

        $this->actingAs($stranger)
            ->get(route('meetings.qr.download', $meeting))
            ->assertOk()
            ->assertSee($strangerToken, false)
            ->assertSee($stranger->company->name, false)
            ->assertDontSee($ownerToken, false)
            ->assertDontSee($owner->company->name, false);

        // Idempotent ensure: repeated show does not mint a second token.
        $this->actingAs($stranger)->get(route('meetings.show', $meeting))->assertOk();
        $this->assertSame(
            1,
            ResellerMeetingCheckInToken::query()
                ->where('meeting_id', $meeting->id)
                ->where('company_id', $stranger->company_id)
                ->count()
        );
    }

    public function test_meeting_detail_banner_is_accessible_without_approved_at(): void
    {
        $reseller = $this->makeResellerWithPermission(['meetings.view']);
        $reseller->company->forceFill(['approved_at' => null])->save();

        $banner = File::query()->create([
            'uuid' => UuidService::generate(),
            'application' => 'ADMIN',
            'entity_type' => 'MEETING',
            'entity_uuid' => UuidService::generate(),
            'category' => FileCategory::MEETING_BANNER->value,
            'disk' => 'feeder',
            'path' => 'meeting-banners/'.Str::uuid().'.png',
            'original_name' => 'meeting-banner.png',
            'extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 4096,
            'checksum' => hash('sha256', Str::random(16)),
            'visibility' => 'PRIVATE',
            'status' => 'ACTIVE',
        ]);

        $meeting = $this->seedMeeting([
            'title' => 'Banner Visibility Meeting',
            'description' => 'Banner should render on details.',
            'location' => 'HQ',
            'scheduled_at' => '2037-10-12 09:00:00',
            'status' => ResellerMeetingStatus::SCHEDULED->value,
            'created_by' => $reseller->id,
            'banner_file_uuid' => $banner->uuid,
        ]);

        $show = $this->actingAs($reseller)->get(route('meetings.show', $meeting));
        $show->assertOk();
        $show->assertSee(route('files.view', $banner->uuid), false);

        $proxy = Mockery::mock(FileProxyService::class);
        $proxy->shouldReceive('view')
            ->once()
            ->with($banner->uuid)
            ->andReturn(response('fake-banner-bytes', 200, ['Content-Type' => 'image/png']));
        $this->app->instance(FileProxyService::class, $proxy);

        $this->actingAs($reseller)
            ->get(route('files.view', $banner->uuid))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_sidebar_unread_counts_drop_immediately_after_marking_notification_read(): void
    {
        $reseller = $this->makeResellerWithPermission(['meetings.view', 'notifications.view']);
        $reseller->company->forceFill(['approved_at' => null])->save();

        $meeting = $this->seedMeeting([
            'title' => 'Unread Badge Meeting',
            'description' => 'Counts',
            'location' => 'HQ',
            'scheduled_at' => '2037-11-20 10:00:00',
            'status' => ResellerMeetingStatus::SCHEDULED->value,
            'created_by' => $reseller->id,
        ]);

        $notification = \Feeder\Core\Models\Notification::query()->create([
            'uuid' => UuidService::generate(),
            'type' => \Feeder\Core\Enums\NotificationType::MEETING_REMINDER->value,
            'title' => 'Meeting scheduled: Unread Badge Meeting',
            'body' => 'Open to mark read',
            'audience_type' => \Feeder\Core\Enums\NotificationAudienceType::SELECTED_RESELLER_USERS->value,
            'publish_at' => now()->subMinute(),
            'expires_at' => now()->addDays(10),
            'created_by' => $reseller->id,
            'meeting_id' => $meeting->id,
            'source_event_type' => 'meeting.published',
            'source_event_key' => 'meet.pub:'.$meeting->id.':'.$reseller->id,
        ]);

        $recipient = \Feeder\Core\Models\NotificationRecipient::query()->create([
            'uuid' => UuidService::generate(),
            'notification_id' => $notification->id,
            'recipient_user_id' => $reseller->id,
            'recipient_company_id' => $reseller->company_id,
            'read_at' => null,
        ]);

        \Feeder\Core\Models\NotificationDelivery::query()->create([
            'uuid' => UuidService::generate(),
            'notification_recipient_id' => $recipient->id,
            'channel' => \Feeder\Core\Enums\NotificationDeliveryChannel::IN_APP->value,
            'status' => \Feeder\Core\Enums\NotificationDeliveryStatus::DELIVERED->value,
            'delivered_at' => now(),
        ]);

        $before = $this->actingAs($reseller)->getJson(route('notifications.live'));
        $before->assertOk();
        $before->assertJsonPath('unread_count', 1);
        $before->assertJsonPath('unread_meeting_count', 1);

        $this->actingAs($reseller)
            ->get(route('notifications.show', $notification))
            ->assertOk();

        $after = $this->actingAs($reseller)->getJson(route('notifications.live'));
        $after->assertOk();
        $after->assertJsonPath('unread_count', 0);
        $after->assertJsonPath('unread_meeting_count', 0);

        $this->assertNotSame(
            $before->json('version'),
            $after->json('version')
        );
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
            'name' => 'Reseller Meet Co '.Str::lower(Str::random(4)),
            'email' => 'meet-'.Str::uuid().'@feeder.local',
            'phone' => '077'.random_int(100000, 999999),
            'registration_number' => 'REG-'.Str::random(6),
            'status' => CompanyStatus::ACTIVE->value,
            'approved_at' => null,
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
                'slug' => 'meet-owner-'.Str::lower(Str::random(4)),
            ],
            [
                'uuid' => UuidService::generate(),
                'company_id' => null,
                'name' => 'Meet Owner Test',
                'description' => 'Meet Owner Test',
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
                    'module' => 'Meetings',
                    'group' => 'Meetings',
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
     * @param  array<string, mixed>  $attributes
     */
    private function seedMeeting(array $attributes): ResellerMeeting
    {
        $scheduled = Carbon::parse((string) $attributes['scheduled_at']);

        return ResellerMeeting::query()->create([
            'uuid' => UuidService::generate(),
            'title' => $attributes['title'],
            'description' => $attributes['description'] ?? null,
            'scheduled_at' => $scheduled,
            'location' => $attributes['location'] ?? null,
            'calendar_month' => $scheduled->format('Y-m'),
            'active_month_key' => $attributes['status'] === ResellerMeetingStatus::SCHEDULED->value
                ? $scheduled->format('Y-m')
                : null,
            'status' => $attributes['status'],
            'banner_file_uuid' => $attributes['banner_file_uuid'] ?? null,
            'created_by' => $attributes['created_by'],
            'updated_by' => $attributes['created_by'],
        ]);
    }

    private function seedToken(
        ResellerMeeting $meeting,
        Company $company,
        User $user,
        string $token,
    ): ResellerMeetingCheckInToken {
        return ResellerMeetingCheckInToken::query()->create([
            'uuid' => UuidService::generate(),
            'meeting_id' => $meeting->id,
            'company_id' => $company->id,
            'user_id' => $user->id,
            'token' => $token,
            'issued_at' => now(),
            'revoked_at' => null,
        ]);
    }

    private function assertMeetingSchemaReady(): void
    {
        $this->assertTrue(
            Schema::hasTable('reseller_meetings') && Schema::hasTable('reseller_meeting_check_in_tokens'),
            'Meeting tables are required for reseller meeting page tests.'
        );
    }
}
