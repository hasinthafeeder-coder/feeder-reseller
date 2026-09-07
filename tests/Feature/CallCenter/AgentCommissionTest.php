<?php

namespace Tests\Feature\CallCenter;

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
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class AgentCommissionTest extends TestCase
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

    public function test_owner_can_update_commission_and_profile_shows_new_rate(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0775005001', 'Commission', 'Agent', '100.00');

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.show', $agent->uuid))
            ->assertOk()
            ->assertSee('Current Commission Rate')
            ->assertSee('LKR 100.00')
            ->assertSee('Changes apply to future orders. Historical commissions are not affected.')
            ->assertSee(route('ui.call-center.agents.commission.update', $agent->uuid), false)
            ->assertSee('Edit Commission');

        $response = $this->actingAs($owner)
            ->post(route('ui.call-center.agents.commission.update', $agent->uuid), [
                'agent_commission_per_order' => '150.00',
            ]);

        $response->assertRedirect(route('ui.call-center.agents.show', $agent->uuid));
        $response->assertSessionHas('success', 'Call Center Agent commission updated successfully.');

        $agent->refresh()->load('profile');
        $this->assertSame('150.00', (string) $agent->profile->agent_commission_per_order);

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.show', $agent->uuid))
            ->assertOk()
            ->assertSee('LKR 150.00')
            ->assertSee('Call Center Agent commission updated successfully.')
            ->assertDontSee('Commission rate updated for preview only');
    }

    public function test_commission_validation_rules(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0775005010', 'Validate', 'Agent', '75.00');
        $url = route('ui.call-center.agents.commission.update', $agent->uuid);

        $this->actingAs($owner)
            ->from(route('ui.call-center.agents.show', $agent->uuid))
            ->post($url, [])
            ->assertRedirect(route('ui.call-center.agents.show', $agent->uuid))
            ->assertSessionHasErrors('agent_commission_per_order');

        $this->actingAs($owner)
            ->from(route('ui.call-center.agents.show', $agent->uuid))
            ->post($url, ['agent_commission_per_order' => '-1'])
            ->assertSessionHasErrors('agent_commission_per_order');

        $this->actingAs($owner)
            ->from(route('ui.call-center.agents.show', $agent->uuid))
            ->post($url, ['agent_commission_per_order' => 'abc'])
            ->assertSessionHasErrors('agent_commission_per_order');

        $this->actingAs($owner)
            ->from(route('ui.call-center.agents.show', $agent->uuid))
            ->post($url, ['agent_commission_per_order' => '12.345'])
            ->assertSessionHasErrors('agent_commission_per_order')
            ->assertSessionHasInput('agent_commission_per_order', '12.345');

        $this->actingAs($owner)
            ->from(route('ui.call-center.agents.show', $agent->uuid))
            ->post($url, ['agent_commission_per_order' => '100000000.01'])
            ->assertSessionHasErrors('agent_commission_per_order');

        $this->assertSame('75.00', (string) $agent->fresh()->profile->agent_commission_per_order);

        $this->actingAs($owner)
            ->post($url, ['agent_commission_per_order' => '0'])
            ->assertRedirect(route('ui.call-center.agents.show', $agent->uuid))
            ->assertSessionHas('success');

        $this->assertSame('0.00', (string) $agent->fresh()->profile->agent_commission_per_order);

        $this->actingAs($owner)
            ->post($url, ['agent_commission_per_order' => '9999.99'])
            ->assertRedirect(route('ui.call-center.agents.show', $agent->uuid))
            ->assertSessionHas('success');

        $this->assertSame('9999.99', (string) $agent->fresh()->profile->agent_commission_per_order);
    }

    public function test_unauthenticated_actor_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0775005020', 'Guest', 'Target', '40.00');

        $this->post(route('ui.call-center.agents.commission.update', $agent->uuid), [
            'agent_commission_per_order' => '99.00',
        ])->assertRedirect();

        $this->assertGuest();
        $this->assertSame('40.00', (string) $agent->fresh()->profile->agent_commission_per_order);
    }

    public function test_actor_without_commission_permission_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0775005021', 'No', 'Perm', '40.00');
        $staff = $this->createLimitedStaff($owner, [
            AgentService::PERMISSION_VIEW,
            AgentService::PERMISSION_UPDATE,
        ]);

        $this->actingAs($staff)
            ->post(route('ui.call-center.agents.commission.update', $agent->uuid), [
                'agent_commission_per_order' => '99.00',
            ])
            ->assertForbidden();

        $this->assertSame('40.00', (string) $agent->fresh()->profile->agent_commission_per_order);

        $this->actingAs($staff)
            ->get(route('ui.call-center.agents.show', $agent->uuid))
            ->assertOk()
            ->assertDontSee('Edit Commission')
            ->assertDontSee(route('ui.call-center.agents.commission.update', $agent->uuid), false);
    }

    public function test_self_management_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0775005030', 'Self', 'Agent', '55.00');

        $this->grantPermissions($agent, [
            AgentService::PERMISSION_VIEW,
            AgentService::PERMISSION_COMMISSION_UPDATE,
        ]);

        $agent = $agent->fresh(['role', 'company', 'profile']);

        $this->actingAs($agent)
            ->post(route('ui.call-center.agents.commission.update', $agent->uuid), [
                'agent_commission_per_order' => '200.00',
            ])
            ->assertForbidden();

        $this->assertSame('55.00', (string) $agent->fresh()->profile->agent_commission_per_order);
    }

    public function test_cross_company_target_is_rejected(): void
    {
        $ownerA = $this->createOwner();
        $ownerB = $this->createOwner();
        $foreign = $this->createAgent($ownerB->company_id, '0775005040', 'Foreign', 'Agent', '60.00');

        $this->actingAs($ownerA)
            ->post(route('ui.call-center.agents.commission.update', $foreign->uuid), [
                'agent_commission_per_order' => '200.00',
            ])
            ->assertNotFound();

        $this->assertSame('60.00', (string) $foreign->fresh()->profile->agent_commission_per_order);
    }

    public function test_wrong_user_type_is_rejected(): void
    {
        $owner = $this->createOwner();
        $staff = $this->createUser(
            (int) $owner->company_id,
            $this->staffRole,
            UserType::EMPLOYEE,
            'staff-comm-'.Str::uuid().'@feeder.local',
            '0775005050',
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
            ->post(route('ui.call-center.agents.commission.update', $staff->uuid), [
                'agent_commission_per_order' => '200.00',
            ])
            ->assertNotFound();

        $this->assertSame('10.00', (string) $staff->fresh()->profile->agent_commission_per_order);
    }

    public function test_wrong_role_and_owner_targets_are_rejected(): void
    {
        $owner = $this->createOwner();

        $manager = $this->createUser(
            (int) $owner->company_id,
            $this->managerRole,
            UserType::EMPLOYEE,
            'mgr-comm-'.Str::uuid().'@feeder.local',
            '0775005051',
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
            'owner-comm-'.Str::uuid().'@feeder.local',
            '0775005052',
        );

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.commission.update', $manager->uuid), [
                'agent_commission_per_order' => '200.00',
            ])
            ->assertNotFound();

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.commission.update', $otherOwner->uuid), [
                'agent_commission_per_order' => '200.00',
            ])
            ->assertNotFound();
    }

    public function test_soft_deleted_agent_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0775005060', 'Deleted', 'Agent', '70.00');
        $uuid = $agent->uuid;
        $agent->delete();

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.commission.update', $uuid), [
                'agent_commission_per_order' => '200.00',
            ])
            ->assertNotFound();

        $trashed = User::withTrashed()->where('uuid', $uuid)->firstOrFail();
        $this->assertSame('70.00', (string) $trashed->profile->agent_commission_per_order);
    }

    public function test_malicious_fields_are_ignored_and_protected_identity_is_preserved(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0775005070', 'Safe', 'Fields', '80.00', '199012345670');
        $this->grantOperationalPermission($agent, 'products.view');
        $before = $this->snapshotProtected($agent);
        $foreignCompany = $this->createOwner()->company_id;

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.commission.update', $agent->uuid), [
                'agent_commission_per_order' => '125.50',
                'company_id' => $foreignCompany,
                'user_type' => UserType::OWNER->value,
                'role_id' => $this->ownerRole->id,
                'status' => UserStatus::SUSPENDED->value,
                'email' => 'hacked@feeder.local',
                'phone' => '0775999999',
                'password' => 'HackedPass999!',
                'nic' => 'HACKEDNIC01',
                'permissions' => ['call_center.agents.view'],
                'uuid' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('ui.call-center.agents.show', $agent->uuid))
            ->assertSessionHas('success');

        $agent->refresh()->load(['profile', 'role']);

        $this->assertSame('125.50', (string) $agent->profile->agent_commission_per_order);
        $this->assertSame(UserStatus::ACTIVE, $agent->status);
        $this->assertSame($before['company_id'], (int) $agent->company_id);
        $this->assertSame($before['user_type'], $agent->user_type);
        $this->assertSame($before['role_id'], (int) $agent->role_id);
        $this->assertSame($before['email'], $agent->email);
        $this->assertSame($before['phone'], $agent->phone);
        $this->assertSame($before['uuid'], $agent->uuid);
        $this->assertSame($before['password'], $agent->password);
        $this->assertSame($before['nic'], (string) $agent->profile->nic);
        $this->assertSame($before['permissions'], $this->permissionRows($agent));
        $this->assertTrue(Hash::check('password', $agent->password));
    }

    public function test_commission_change_does_not_affect_status_role_or_permissions(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0775005080', 'Stable', 'Status', '90.00');
        $this->grantOperationalPermission($agent, 'products.view');

        $this->assertSame(UserStatus::ACTIVE, $agent->status);

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.commission.update', $agent->uuid), [
                'agent_commission_per_order' => '110.00',
            ])
            ->assertRedirect();

        $agent->refresh()->load(['profile', 'role']);

        $this->assertSame(UserStatus::ACTIVE, $agent->status);
        $this->assertSame(AgentService::ROLE_SLUG, $agent->role->slug);
        $this->assertSame('110.00', (string) $agent->profile->agent_commission_per_order);
        $this->assertTrue(
            DB::table('user_permissions')->where('user_id', $agent->id)->exists()
        );

        $agent->forceFill(['status' => UserStatus::SUSPENDED->value])->save();

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.commission.update', $agent->uuid), [
                'agent_commission_per_order' => '120.00',
            ])
            ->assertRedirect();

        $agent->refresh();
        $this->assertSame(UserStatus::SUSPENDED, $agent->status);
        $this->assertSame('120.00', (string) $agent->fresh()->profile->agent_commission_per_order);
    }

    public function test_existing_commission_is_loaded_and_replaced_only(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0775005090', 'Loaded', 'Rate', '42.50');

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.show', $agent->uuid))
            ->assertOk()
            ->assertSee('value="42.50"', false)
            ->assertSee('LKR 42.50');

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.commission.update', $agent->uuid), [
                'agent_commission_per_order' => '50',
            ])
            ->assertRedirect();

        $this->assertSame('50.00', (string) $agent->fresh()->profile->agent_commission_per_order);
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
            'limited-comm-'.Str::uuid().'@feeder.local',
            '0775'.random_int(100000, 999999),
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
     *     nic: string,
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
            'nic' => (string) ($agent->profile?->nic ?? ''),
            'permissions' => $this->permissionRows($agent),
        ];
    }

    /**
     * @return list<array{0: int, 1: int}>
     */
    private function permissionRows(User $agent): array
    {
        return DB::table('user_permissions')
            ->where('user_id', $agent->id)
            ->orderBy('permission_id')
            ->get(['permission_id', 'allowed'])
            ->map(fn ($row) => [(int) $row->permission_id, (int) $row->allowed])
            ->all();
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
