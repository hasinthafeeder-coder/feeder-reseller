<?php

namespace Tests\Feature\CallCenter;

use App\Services\CallCenter\AgentService;
use Feeder\Core\Authorization\Services\PermissionService;
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

class AgentPermissionsTest extends TestCase
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

    public function test_owner_can_grant_operational_permission_by_slug_and_profile_reflects_it(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0776006001', 'Grant', 'Agent');
        $productsView = $this->resellerPermission('products.view');

        $this->assertFalse($agent->hasPermission('products.view'));
        $this->assertSame(0, $this->overrideCount($agent, $productsView->id));

        $response = $this->actingAs($owner)
            ->post(route('ui.call-center.agents.permissions.update', $agent->uuid), [
                'permissions' => ['products.view'],
            ]);

        $response->assertRedirect(route('ui.call-center.agents.show', $agent->uuid));
        $response->assertSessionHas('success', 'Call Center Agent permissions updated successfully.');

        $this->assertOverrideState($agent, $productsView->id, true);
        $this->assertTrue($agent->fresh()->hasPermission('products.view'));

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.show', $agent->uuid))
            ->assertOk()
            ->assertSee('View Products')
            ->assertDontSee('View Orders')
            ->assertDontSee('View Call Center Agents')
            ->assertDontSee('call_center.agents.view');
    }

    public function test_owner_can_grant_operational_permission_by_id(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0776006002', 'GrantId', 'Agent');
        $dashboardView = $this->resellerPermission('dashboard.view');

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.permissions.update', $agent->uuid), [
                'permissions' => [$dashboardView->id],
            ])
            ->assertRedirect(route('ui.call-center.agents.show', $agent->uuid));

        $this->assertOverrideState($agent, $dashboardView->id, true);
        $this->assertTrue($agent->fresh()->hasPermission('dashboard.view'));
    }

    public function test_owner_can_revoke_override_and_inheritance_resumes(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0776006010', 'Revoke', 'Agent');
        $productsView = $this->resellerPermission('products.view');

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.permissions.update', $agent->uuid), [
                'permissions' => ['products.view', 'dashboard.view'],
            ])
            ->assertRedirect();

        $this->assertOverrideState($agent, $productsView->id, true);
        $this->assertTrue($agent->fresh()->hasPermission('products.view'));

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.permissions.update', $agent->uuid), [
                'permissions' => ['dashboard.view'],
            ])
            ->assertRedirect();

        $this->assertSame(0, $this->overrideCount($agent, $productsView->id));
        $this->assertFalse($agent->fresh()->hasPermission('products.view'));
        $this->assertTrue($agent->fresh()->hasPermission('dashboard.view'));
    }

    public function test_explicit_deny_is_stored_and_respected_over_role_inheritance(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0776006020', 'Deny', 'Agent');
        $productsView = $this->resellerPermission('products.view');

        $this->agentRole->permissions()->syncWithoutDetaching([$productsView->id]);
        app(PermissionService::class)->forgetCache($agent);

        $this->assertTrue($agent->fresh()->hasPermission('products.view'));

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.permissions.update', $agent->uuid), [
                'permissions' => [],
                'denied' => ['products.view'],
            ])
            ->assertRedirect();

        $this->assertOverrideState($agent, $productsView->id, false);
        $this->assertFalse($agent->fresh()->hasPermission('products.view'));

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.show', $agent->uuid))
            ->assertOk()
            ->assertSee('View Products (denied)');
    }

    public function test_permission_mutation_invalidates_effective_permission_cache(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0776006030', 'Cache', 'Agent');

        $this->assertFalse($agent->hasPermission('products.view'));

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.permissions.update', $agent->uuid), [
                'permissions' => ['products.view'],
            ])
            ->assertRedirect();

        $fresh = $agent->fresh(['role']);
        $this->assertTrue($fresh->hasPermission('products.view'));
        $this->assertTrue(app(PermissionService::class)->hasPermission($fresh, 'products.view'));
    }

    public function test_create_and_edit_screens_show_real_operational_permissions_not_mock_keys(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0776006040', 'Ui', 'Agent');

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.create'))
            ->assertOk()
            ->assertSee('View Products')
            ->assertSee('View Dashboard')
            ->assertSee('View Team Structure')
            ->assertSee('name="permissions[]"', false)
            ->assertSee('value="products.view"', false)
            ->assertDontSee('view_orders')
            ->assertDontSee('View Orders')
            ->assertDontSee('View Call Center Agents')
            ->assertDontSee('call_center.agents.create');

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.edit', $agent->uuid))
            ->assertOk()
            ->assertSee('View Products')
            ->assertSee('Save Permissions')
            ->assertSee(route('ui.call-center.agents.permissions.update', $agent->uuid), false)
            ->assertDontSee('view_orders')
            ->assertDontSee('View Orders')
            ->assertDontSee('View Call Center Agents');
    }

    public function test_create_agent_persists_real_operational_permissions_and_ignores_preview_keys(): void
    {
        $owner = $this->createOwner();
        $phone = '0776006050';
        $productsView = $this->resellerPermission('products.view');

        $this->actingAs($owner)->post(route('ui.call-center.agents.store'), [
            'first_name' => 'Create',
            'last_name' => 'Perms',
            'phone' => $phone,
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
            'agent_commission_per_order' => '80.00',
            'permissions' => ['products.view', 'view_orders', 'confirm_orders'],
        ])->assertRedirect();

        $agent = User::query()->where('phone', $phone)->firstOrFail();

        $this->assertOverrideState($agent, $productsView->id, true);
        $this->assertTrue($agent->fresh()->hasPermission('products.view'));
        $this->assertSame(1, DB::table('user_permissions')->where('user_id', $agent->id)->count());
        $this->assertNull(Permission::query()->where('slug', 'view_orders')->first());
    }

    public function test_generic_edit_endpoint_does_not_modify_permissions(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0776006060', 'Edit', 'Safe');
        $productsView = $this->resellerPermission('products.view');

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.permissions.update', $agent->uuid), [
                'permissions' => ['products.view'],
            ])
            ->assertRedirect();

        $this->actingAs($owner)->put(route('ui.call-center.agents.update', $agent->uuid), [
            'first_name' => 'Edited',
            'last_name' => 'Safe',
            'phone' => '0776006060',
            'permissions' => ['dashboard.view', AgentService::PERMISSION_CREATE],
        ])->assertRedirect();

        $agent->refresh()->load('profile');
        $this->assertSame('Edited', $agent->profile->first_name);
        $this->assertOverrideState($agent, $productsView->id, true);
        $this->assertSame(1, DB::table('user_permissions')->where('user_id', $agent->id)->count());
        $this->assertFalse($agent->fresh()->hasPermission('dashboard.view'));
        $this->assertFalse($agent->fresh()->hasPermission(AgentService::PERMISSION_CREATE));
    }

    public function test_actor_without_permissions_update_cannot_modify_permissions(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0776006070', 'No', 'Perm');
        $staff = $this->createLimitedStaff($owner, [
            AgentService::PERMISSION_VIEW,
            AgentService::PERMISSION_UPDATE,
        ]);

        $this->actingAs($staff)
            ->post(route('ui.call-center.agents.permissions.update', $agent->uuid), [
                'permissions' => ['products.view'],
            ])
            ->assertForbidden();

        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $agent->id)->count());
    }

    public function test_activate_permission_alone_cannot_modify_permissions(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0776006071', 'Activate', 'Only');
        $staff = $this->createLimitedStaff($owner, [
            AgentService::PERMISSION_VIEW,
            AgentService::PERMISSION_ACTIVATE,
        ]);

        $this->actingAs($staff)
            ->post(route('ui.call-center.agents.permissions.update', $agent->uuid), [
                'permissions' => ['products.view'],
            ])
            ->assertForbidden();

        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $agent->id)->count());
    }

    public function test_commission_permission_alone_cannot_modify_permissions(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0776006072', 'Commission', 'Only');
        $staff = $this->createLimitedStaff($owner, [
            AgentService::PERMISSION_VIEW,
            AgentService::PERMISSION_COMMISSION_UPDATE,
        ]);

        $this->actingAs($staff)
            ->post(route('ui.call-center.agents.permissions.update', $agent->uuid), [
                'permissions' => ['products.view'],
            ])
            ->assertForbidden();

        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $agent->id)->count());
    }

    public function test_unauthenticated_actor_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0776006080', 'Guest', 'Target');

        $this->post(route('ui.call-center.agents.permissions.update', $agent->uuid), [
            'permissions' => ['products.view'],
        ])->assertRedirect();

        $this->assertGuest();
        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $agent->id)->count());
    }

    public function test_self_management_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0776006090', 'Self', 'Agent');

        $this->grantPermissions($agent, [
            AgentService::PERMISSION_VIEW,
            AgentService::PERMISSION_PERMISSIONS_UPDATE,
        ]);

        $agent = $agent->fresh(['role', 'company', 'profile']);
        $before = $this->permissionRows($agent);

        $this->actingAs($agent)
            ->post(route('ui.call-center.agents.permissions.update', $agent->uuid), [
                'permissions' => ['products.view'],
            ])
            ->assertForbidden();

        $this->assertSame($before, $this->permissionRows($agent->fresh()));
        $this->assertFalse($agent->fresh()->hasPermission('products.view'));
    }

    public function test_cross_company_target_is_rejected(): void
    {
        $ownerA = $this->createOwner();
        $ownerB = $this->createOwner();
        $foreign = $this->createAgent($ownerB->company_id, '0776006100', 'Foreign', 'Agent');

        $this->actingAs($ownerA)
            ->post(route('ui.call-center.agents.permissions.update', $foreign->uuid), [
                'permissions' => ['products.view'],
            ])
            ->assertNotFound();

        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $foreign->id)->count());
    }

    public function test_wrong_user_type_is_rejected(): void
    {
        $owner = $this->createOwner();
        $staff = $this->createUser(
            (int) $owner->company_id,
            $this->staffRole,
            UserType::EMPLOYEE,
            'staff-perm-'.Str::uuid().'@feeder.local',
            '0776006110',
        );

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.permissions.update', $staff->uuid), [
                'permissions' => ['products.view'],
            ])
            ->assertNotFound();
    }

    public function test_wrong_role_and_owner_targets_are_rejected(): void
    {
        $owner = $this->createOwner();

        $manager = $this->createUser(
            (int) $owner->company_id,
            $this->managerRole,
            UserType::EMPLOYEE,
            'mgr-perm-'.Str::uuid().'@feeder.local',
            '0776006111',
        );

        $otherOwner = $this->createUser(
            (int) $owner->company_id,
            $this->ownerRole,
            UserType::OWNER,
            'owner-perm-'.Str::uuid().'@feeder.local',
            '0776006112',
        );

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.permissions.update', $manager->uuid), [
                'permissions' => ['products.view'],
            ])
            ->assertNotFound();

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.permissions.update', $otherOwner->uuid), [
                'permissions' => ['products.view'],
            ])
            ->assertNotFound();
    }

    public function test_soft_deleted_agent_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0776006120', 'Deleted', 'Agent');
        $uuid = $agent->uuid;
        $agent->delete();

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.permissions.update', $uuid), [
                'permissions' => ['products.view'],
            ])
            ->assertNotFound();

        $trashed = User::withTrashed()->where('uuid', $uuid)->firstOrFail();
        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $trashed->id)->count());
    }

    public function test_management_permissions_cannot_be_assigned_by_slug_or_id(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0776006130', 'Mgmt', 'Block');
        $url = route('ui.call-center.agents.permissions.update', $agent->uuid);

        foreach (AgentService::MANAGEMENT_PERMISSION_SLUGS as $slug) {
            $permission = $this->resellerPermission($slug);

            $this->actingAs($owner)
                ->from(route('ui.call-center.agents.edit', $agent->uuid))
                ->post($url, ['permissions' => [$slug]])
                ->assertRedirect(route('ui.call-center.agents.edit', $agent->uuid))
                ->assertSessionHasErrors('permissions');

            $this->actingAs($owner)
                ->from(route('ui.call-center.agents.edit', $agent->uuid))
                ->post($url, ['permissions' => [$permission->id]])
                ->assertRedirect(route('ui.call-center.agents.edit', $agent->uuid))
                ->assertSessionHasErrors('permissions');

            $this->assertSame(
                0,
                DB::table('user_permissions')
                    ->where('user_id', $agent->id)
                    ->where('permission_id', $permission->id)
                    ->count(),
                "Management permission {$slug} must never become an Agent override."
            );
        }

        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $agent->id)->count());
        $this->assertFalse($agent->fresh()->hasPermission(AgentService::PERMISSION_VIEW));
        $this->assertFalse($agent->fresh()->hasPermission(AgentService::PERMISSION_PERMISSIONS_UPDATE));
    }

    public function test_admin_and_supplier_permissions_cannot_be_assigned_by_slug_or_id(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0776006140', 'Portal', 'Block');
        $url = route('ui.call-center.agents.permissions.update', $agent->uuid);

        $adminPermission = $this->portalPermission('resellers.view', PortalCode::ADMIN);
        $supplierPermission = $this->portalPermission('grns.view', PortalCode::SUPPLIER);

        foreach ([$adminPermission, $supplierPermission] as $foreign) {
            $this->actingAs($owner)
                ->from(route('ui.call-center.agents.edit', $agent->uuid))
                ->post($url, ['permissions' => [$foreign->slug]])
                ->assertRedirect(route('ui.call-center.agents.edit', $agent->uuid))
                ->assertSessionHasErrors('permissions');

            $this->actingAs($owner)
                ->from(route('ui.call-center.agents.edit', $agent->uuid))
                ->post($url, ['permissions' => [$foreign->id]])
                ->assertRedirect(route('ui.call-center.agents.edit', $agent->uuid))
                ->assertSessionHasErrors('permissions');

            $this->assertSame(
                0,
                DB::table('user_permissions')
                    ->where('user_id', $agent->id)
                    ->where('permission_id', $foreign->id)
                    ->count()
            );
        }
    }

    public function test_permission_update_does_not_modify_protected_identity_fields(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0776006150', 'Safe', 'Identity', '80.00', '199012345650');
        $before = $this->snapshotProtected($agent);
        $foreignCompany = $this->createOwner()->company_id;

        $this->actingAs($owner)
            ->post(route('ui.call-center.agents.permissions.update', $agent->uuid), [
                'permissions' => ['products.view'],
                'company_id' => $foreignCompany,
                'user_type' => UserType::OWNER->value,
                'role_id' => $this->ownerRole->id,
                'status' => UserStatus::SUSPENDED->value,
                'email' => 'hacked@feeder.local',
                'phone' => '0776999999',
                'password' => 'HackedPass999!',
                'nic' => 'HACKEDNIC01',
                'agent_commission_per_order' => '999.00',
                'uuid' => (string) Str::uuid(),
            ])
            ->assertRedirect(route('ui.call-center.agents.show', $agent->uuid));

        $agent->refresh()->load(['profile', 'role']);
        $productsView = $this->resellerPermission('products.view');

        $this->assertOverrideState($agent, $productsView->id, true);
        $this->assertSame($before['company_id'], (int) $agent->company_id);
        $this->assertSame($before['user_type'], $agent->user_type);
        $this->assertSame($before['role_id'], (int) $agent->role_id);
        $this->assertSame(UserStatus::ACTIVE, $agent->status);
        $this->assertSame($before['email'], $agent->email);
        $this->assertSame($before['phone'], $agent->phone);
        $this->assertSame($before['uuid'], $agent->uuid);
        $this->assertSame($before['password'], $agent->password);
        $this->assertSame($before['nic'], (string) $agent->profile->nic);
        $this->assertSame($before['commission'], (string) $agent->profile->agent_commission_per_order);
        $this->assertTrue(Hash::check('password', $agent->password));
        $this->assertSame(AgentService::ROLE_SLUG, $agent->role->slug);
        $this->assertSame(UserType::EMPLOYEE->value, $agent->user_type);
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
            'limited-perm-'.Str::uuid().'@feeder.local',
            '0776'.random_int(100000, 999999),
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

    private function resellerPermission(string $slug): Permission
    {
        return $this->portalPermission($slug, PortalCode::RESELLER);
    }

    private function portalPermission(string $slug, PortalCode $portal): Permission
    {
        $permission = Permission::query()
            ->where('slug', $slug)
            ->whereNull('deleted_at')
            ->whereHas('portal', fn ($query) => $query->where('code', $portal->value))
            ->first();

        $this->assertNotNull($permission, "Missing {$portal->value} permission: {$slug}");

        return $permission;
    }

    private function assertOverrideState(User $user, int $permissionId, bool $allowed): void
    {
        $row = DB::table('user_permissions')
            ->where('user_id', $user->id)
            ->where('permission_id', $permissionId)
            ->first();

        $this->assertNotNull($row, 'Expected user_permissions override row.');
        $this->assertSame($allowed, (bool) $row->allowed);
    }

    private function overrideCount(User $user, int $permissionId): int
    {
        return DB::table('user_permissions')
            ->where('user_id', $user->id)
            ->where('permission_id', $permissionId)
            ->count();
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

    /**
     * @return array<string, mixed>
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
            'commission' => (string) ($agent->profile?->agent_commission_per_order ?? ''),
        ];
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
