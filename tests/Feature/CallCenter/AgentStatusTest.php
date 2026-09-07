<?php

namespace Tests\Feature\CallCenter;

use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\LoginService;
use App\Services\CallCenter\AgentService;
use Feeder\Core\Authorization\Services\UserPermissionService;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Permission;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\User;
use Feeder\Core\Models\UserProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class AgentStatusTest extends TestCase
{
    use UsesMysqlTestDatabase;

    private Role $ownerRole;

    private Role $agentRole;

    private Role $staffRole;

    private Role $managerRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        config(['cache.default' => 'array']);

        $this->ownerRole = $this->resellerRole('owner');
        $this->agentRole = $this->resellerRole('call-center-agent');
        $this->staffRole = $this->resellerRole('staff');
        $this->managerRole = $this->resellerRole('manager');
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_owner_can_deactivate_active_agent(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0774004001', 'Status', 'Agent');
        $this->assertSame(UserStatus::ACTIVE, $agent->status);

        $response = $this->actingAs($owner)
            ->post(route('ui.call-center.agents.deactivate', $agent->uuid));

        $response->assertRedirect(route('ui.call-center.agents.show', $agent->uuid));
        $response->assertSessionHas('success', 'Call Center Agent deactivated successfully.');

        $agent->refresh();
        $this->assertSame(UserStatus::SUSPENDED, $agent->status);

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.show', $agent->uuid))
            ->assertOk()
            ->assertSee('Inactive')
            ->assertSee(route('ui.call-center.agents.activate', $agent->uuid), false)
            ->assertDontSee(route('ui.call-center.agents.deactivate', $agent->uuid), false);

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Status Agent');

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.index', ['status' => 'active']))
            ->assertOk()
            ->assertDontSee('Status Agent');
    }

    public function test_owner_can_activate_suspended_agent(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0774004002', 'Paused', 'Agent');
        $agent->forceFill(['status' => UserStatus::SUSPENDED->value])->save();

        $response = $this->actingAs($owner)
            ->post(route('ui.call-center.agents.activate', $agent->uuid));

        $response->assertRedirect(route('ui.call-center.agents.show', $agent->uuid));
        $response->assertSessionHas('success', 'Call Center Agent activated successfully.');

        $agent->refresh();
        $this->assertSame(UserStatus::ACTIVE, $agent->status);

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.show', $agent->uuid))
            ->assertOk()
            ->assertDontSee('Inactive')
            ->assertSee(route('ui.call-center.agents.deactivate', $agent->uuid), false)
            ->assertDontSee(route('ui.call-center.agents.activate', $agent->uuid), false);

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('Paused Agent');

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertDontSee('Paused Agent');
    }

    public function test_actor_with_activate_permission_cannot_deactivate(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0774004010', 'Keep', 'Active');
        $actor = $this->createLimitedStaff($owner, [
            AgentService::PERMISSION_VIEW,
            AgentService::PERMISSION_ACTIVATE,
        ]);

        $this->actingAs($actor)
            ->post(route('ui.call-center.agents.deactivate', $agent->uuid))
            ->assertForbidden();

        $this->assertSame(UserStatus::ACTIVE, $agent->fresh()->status);
    }

    public function test_actor_with_deactivate_permission_cannot_activate(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0774004011', 'Keep', 'Suspended');
        $agent->forceFill(['status' => UserStatus::SUSPENDED->value])->save();
        $actor = $this->createLimitedStaff($owner, [
            AgentService::PERMISSION_VIEW,
            AgentService::PERMISSION_DEACTIVATE,
        ]);

        $this->actingAs($actor)
            ->post(route('ui.call-center.agents.activate', $agent->uuid))
            ->assertForbidden();

        $this->assertSame(UserStatus::SUSPENDED, $agent->fresh()->status);
    }

    public function test_suspended_agent_cannot_authenticate_and_active_agent_can(): void
    {
        $owner = $this->createOwner();
        $phone = '0774004020';
        $agent = $this->createAgent($owner->company_id, $phone, 'Login', 'Agent');

        auth()->logout();

        $authenticated = $this->attemptLogin($phone, 'password');
        $this->assertSame($agent->id, $authenticated->id);

        auth()->logout();

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.deactivate', $agent->uuid))
            ->assertRedirect();

        $this->assertSame(UserStatus::SUSPENDED, $agent->fresh()->status);

        auth()->logout();

        try {
            $this->attemptLogin($phone, 'password');
            $this->fail('Suspended agent must not authenticate.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('identifier', $exception->errors());
            $this->assertSame(
                'Your account has been suspended.',
                $exception->errors()['identifier'][0]
            );
        }
    }

    public function test_activating_already_active_agent_is_harmless(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0774004030', 'Already', 'Active', '88.00');
        $this->grantOperationalPermission($agent, 'products.view');
        $before = $this->snapshotProtected($agent);

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.activate', $agent->uuid))
            ->assertRedirect(route('ui.call-center.agents.show', $agent->uuid))
            ->assertSessionHas('success', 'Call Center Agent activated successfully.');

        $agent->refresh()->load(['profile', 'role']);
        $this->assertSame(UserStatus::ACTIVE, $agent->status);
        $this->assertProtectedUnchanged($before, $agent);
    }

    public function test_deactivating_already_suspended_agent_is_harmless(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0774004031', 'Already', 'Suspended', '88.00');
        $this->grantOperationalPermission($agent, 'products.view');
        $agent->forceFill(['status' => UserStatus::SUSPENDED->value])->save();
        $before = $this->snapshotProtected($agent->fresh(['profile']));

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.deactivate', $agent->uuid))
            ->assertRedirect(route('ui.call-center.agents.show', $agent->uuid))
            ->assertSessionHas('success', 'Call Center Agent deactivated successfully.');

        $agent->refresh()->load(['profile', 'role']);
        $this->assertSame(UserStatus::SUSPENDED, $agent->status);
        $this->assertProtectedUnchanged($before, $agent);
    }

    public function test_unauthenticated_actor_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0774004040');

        $this->post(route('ui.call-center.agents.deactivate', $agent->uuid))
            ->assertRedirect();
        $this->assertGuest();
        $this->assertSame(UserStatus::ACTIVE, $agent->fresh()->status);

        $agent->forceFill(['status' => UserStatus::SUSPENDED->value])->save();

        $this->post(route('ui.call-center.agents.activate', $agent->uuid))
            ->assertRedirect();
        $this->assertGuest();
        $this->assertSame(UserStatus::SUSPENDED, $agent->fresh()->status);
    }

    public function test_actor_without_appropriate_permission_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0774004041', 'No', 'Perms');
        $staff = $this->createUser(
            (int) $owner->company_id,
            $this->staffRole,
            UserType::EMPLOYEE,
            'staff-status-'.Str::uuid().'@feeder.local',
            '0774004049',
        );

        $this->actingAs($staff)
            ->post(route('ui.call-center.agents.deactivate', $agent->uuid))
            ->assertForbidden();
        $this->assertSame(UserStatus::ACTIVE, $agent->fresh()->status);

        $agent->forceFill(['status' => UserStatus::SUSPENDED->value])->save();

        $this->actingAs($staff)
            ->post(route('ui.call-center.agents.activate', $agent->uuid))
            ->assertForbidden();
        $this->assertSame(UserStatus::SUSPENDED, $agent->fresh()->status);
    }

    public function test_self_management_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0774004050');

        $this->grantPermissions($agent, [
            AgentService::PERMISSION_VIEW,
            AgentService::PERMISSION_ACTIVATE,
            AgentService::PERMISSION_DEACTIVATE,
        ]);

        $agent = $agent->fresh(['role', 'company']);

        $this->actingAs($agent)
            ->post(route('ui.call-center.agents.deactivate', $agent->uuid))
            ->assertForbidden();
        $this->assertSame(UserStatus::ACTIVE, $agent->fresh()->status);

        $agent->forceFill(['status' => UserStatus::SUSPENDED->value])->save();

        $this->actingAs($agent)
            ->post(route('ui.call-center.agents.activate', $agent->uuid))
            ->assertForbidden();
        $this->assertSame(UserStatus::SUSPENDED, $agent->fresh()->status);
    }

    public function test_cross_company_target_is_rejected(): void
    {
        $ownerA = $this->createOwner();
        $ownerB = $this->createOwner();
        $foreign = $this->createAgent($ownerB->company_id, '0774004060', 'Foreign', 'Agent');

        $this->actingAs($ownerA)
            ->post(route('ui.call-center.agents.deactivate', $foreign->uuid))
            ->assertNotFound();
        $this->assertSame(UserStatus::ACTIVE, $foreign->fresh()->status);

        $foreign->forceFill(['status' => UserStatus::SUSPENDED->value])->save();

        $this->actingAs($ownerA)
            ->post(route('ui.call-center.agents.activate', $foreign->uuid))
            ->assertNotFound();
        $this->assertSame(UserStatus::SUSPENDED, $foreign->fresh()->status);
    }

    public function test_wrong_user_type_is_rejected(): void
    {
        $owner = $this->createOwner();
        $staff = $this->createUser(
            (int) $owner->company_id,
            $this->staffRole,
            UserType::EMPLOYEE,
            'staff-type-status-'.Str::uuid().'@feeder.local',
            '0774004070',
        );
        UserProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $staff->id,
            'first_name' => 'Staff',
            'last_name' => 'Member',
            'nic' => null,
            'agent_commission_per_order' => '10.00',
        ]);

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.deactivate', $staff->uuid))
            ->assertNotFound();
        $this->assertSame(UserStatus::ACTIVE, $staff->fresh()->status);
    }

    public function test_wrong_role_and_owner_targets_are_rejected(): void
    {
        $owner = $this->createOwner();

        $manager = $this->createUser(
            (int) $owner->company_id,
            $this->managerRole,
            UserType::EMPLOYEE,
            'mgr-status-'.Str::uuid().'@feeder.local',
            '0774004071',
        );
        UserProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $manager->id,
            'first_name' => 'Company',
            'last_name' => 'Manager',
            'nic' => null,
            'agent_commission_per_order' => '10.00',
        ]);

        $otherOwner = $this->createUser(
            (int) $owner->company_id,
            $this->ownerRole,
            UserType::OWNER,
            'owner-status-'.Str::uuid().'@feeder.local',
            '0774004072',
        );

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.deactivate', $manager->uuid))
            ->assertNotFound();
        $this->assertSame(UserStatus::ACTIVE, $manager->fresh()->status);

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.deactivate', $otherOwner->uuid))
            ->assertNotFound();
        $this->assertSame(UserStatus::ACTIVE, $otherOwner->fresh()->status);
    }

    public function test_soft_deleted_agent_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0774004080');
        $uuid = $agent->uuid;
        $agent->delete();

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.deactivate', $uuid))
            ->assertNotFound();

        $trashed = User::withTrashed()->where('uuid', $uuid)->firstOrFail();
        $this->assertSame(UserStatus::ACTIVE, $trashed->status);
    }

    public function test_deactivate_and_activate_change_only_status(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0774004090', 'Safe', 'Fields', '55.00');
        $this->grantOperationalPermission($agent, 'products.view');
        $before = $this->snapshotProtected($agent);

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.deactivate', $agent->uuid))
            ->assertRedirect();

        $agent->refresh()->load(['profile', 'role']);
        $this->assertSame(UserStatus::SUSPENDED, $agent->status);
        $this->assertProtectedUnchanged($before, $agent);

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.activate', $agent->uuid))
            ->assertRedirect();

        $agent->refresh()->load(['profile', 'role']);
        $this->assertSame(UserStatus::ACTIVE, $agent->status);
        $this->assertProtectedUnchanged($before, $agent);
    }

    public function test_profile_status_form_posts_to_deactivate_for_active_agent(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0774004091', 'Form', 'Active');

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.show', $agent->uuid))
            ->assertOk()
            ->assertSee(route('ui.call-center.agents.deactivate', $agent->uuid), false)
            ->assertSee('name="_token"', false)
            ->assertDontSee('does not change agent status yet');
    }

    private function attemptLogin(string $identifier, string $password): User
    {
        $this->startSession();

        $request = LoginRequest::create('/login', 'POST', [
            'identifier' => $identifier,
            'password' => $password,
        ]);
        $request->setContainer(app());
        $request->setRedirector(app('redirect'));
        $request->setLaravelSession(app('session.store'));
        app()->instance('request', $request);

        return app(LoginService::class)->login($request, PortalCode::RESELLER);
    }

    /**
     * @param  list<string>  $slugs
     */
    private function createLimitedStaff(User $owner, array $slugs): User
    {
        $staff = $this->createUser(
            (int) $owner->company_id,
            $this->staffRole,
            UserType::EMPLOYEE,
            'limited-'.Str::uuid().'@feeder.local',
            '0774'.random_int(100000, 999999),
        );

        $this->grantPermissions($staff, $slugs);

        return $staff->fresh(['role', 'company']);
    }

    /**
     * @param  list<string>  $slugs
     */
    private function grantPermissions(User $user, array $slugs): void
    {
        app(UserPermissionService::class)->syncAllowed(
            $user,
            Permission::query()
                ->whereIn('slug', $slugs)
                ->whereHas('portal', fn ($query) => $query->where('code', PortalCode::RESELLER->value))
                ->pluck('id')
                ->all()
        );
    }

    private function grantOperationalPermission(User $user, string $slug): void
    {
        $permission = Permission::query()
            ->where('slug', $slug)
            ->whereNull('deleted_at')
            ->whereHas('portal', fn ($query) => $query->where('code', PortalCode::RESELLER->value))
            ->first();

        $this->assertNotNull($permission, "Missing RESELLER permission: {$slug}");

        app(UserPermissionService::class)->syncAllowed($user, [$permission->id]);
    }

    /**
     * @return array{
     *     company_id: int,
     *     user_type: mixed,
     *     role_id: int,
     *     email: string,
     *     phone: string,
     *     uuid: string,
     *     password: string,
     *     commission: string,
     *     permissions: list<array{0: int, 1: int}>
     * }
     */
    private function snapshotProtected(User $agent): array
    {
        $agent->loadMissing('profile');

        return [
            'company_id' => (int) $agent->company_id,
            'user_type' => $agent->user_type,
            'role_id' => (int) $agent->role_id,
            'email' => $agent->email,
            'phone' => $agent->phone,
            'uuid' => $agent->uuid,
            'password' => $agent->password,
            'commission' => (string) $agent->profile?->agent_commission_per_order,
            'permissions' => DB::table('user_permissions')
                ->where('user_id', $agent->id)
                ->orderBy('permission_id')
                ->get(['permission_id', 'allowed'])
                ->map(fn ($row) => [(int) $row->permission_id, (int) $row->allowed])
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     */
    private function assertProtectedUnchanged(array $before, User $agent): void
    {
        $after = $this->snapshotProtected($agent);

        $this->assertSame($before['company_id'], $after['company_id']);
        $this->assertSame($before['user_type'], $after['user_type']);
        $this->assertSame($before['role_id'], $after['role_id']);
        $this->assertSame($before['email'], $after['email']);
        $this->assertSame($before['phone'], $after['phone']);
        $this->assertSame($before['uuid'], $after['uuid']);
        $this->assertSame($before['password'], $after['password']);
        $this->assertSame($before['commission'], $after['commission']);
        $this->assertSame($before['permissions'], $after['permissions']);
        $this->assertSame(UserType::EMPLOYEE->value, $agent->user_type);
        $this->assertSame(AgentService::ROLE_SLUG, $agent->role->slug);
    }

    private function createOwner(): User
    {
        $portal = Portal::query()->firstOrCreate(
            ['code' => PortalCode::RESELLER->value],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Reseller Portal',
                'subdomain' => 'reseller',
                'description' => 'Reseller Portal',
                'is_active' => true,
            ]
        );

        $suffix = Str::lower(Str::random(6));
        $phone = '070'.random_int(1000000, 9999999);

        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'portal_id' => $portal->id,
            'name' => 'Reseller '.$suffix,
            'email' => 'company-'.$suffix.'@feeder.local',
            'phone' => $phone,
            'registration_number' => 'REG-'.$suffix,
            'status' => CompanyStatus::ACTIVE->value,
        ]);

        $owner = $this->createUser(
            $company->id,
            $this->ownerRole,
            UserType::OWNER,
            'owner-'.$suffix.'@feeder.local',
            $phone,
        );

        $company->forceFill(['owner_user_id' => $owner->id])->save();

        return $owner->fresh(['company', 'role.portal']);
    }

    private function createAgent(
        int|string $companyId,
        string $phone,
        string $firstName = 'Agent',
        string $lastName = 'User',
        string $commission = '50.00',
        ?string $nic = null,
    ): User {
        $agent = $this->createUser(
            (int) $companyId,
            $this->agentRole,
            UserType::EMPLOYEE,
            sprintf('%s@reseller.local', $phone),
            $phone,
        );

        UserProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $agent->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'nic' => $nic,
            'agent_commission_per_order' => $commission,
        ]);

        return $agent->fresh(['profile', 'role.portal', 'company.portal']);
    }

    private function createUser(
        int $companyId,
        Role $role,
        UserType $userType,
        string $email,
        string $phone,
    ): User {
        return User::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $companyId,
            'role_id' => $role->id,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make('password'),
            'user_type' => $userType->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
            'is_master_reseller' => false,
        ])->fresh(['role', 'company']);
    }

    private function resellerRole(string $slug): Role
    {
        $role = Role::query()
            ->where('slug', $slug)
            ->whereNull('deleted_at')
            ->whereHas('portal', fn ($query) => $query->where('code', PortalCode::RESELLER->value))
            ->first();

        $this->assertNotNull($role, "Missing RESELLER role: {$slug}");

        return $role;
    }
}
