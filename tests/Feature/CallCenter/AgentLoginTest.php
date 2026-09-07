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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

/**
 * O1.4-D5-C — Call Center Agent login and access control.
 *
 * Agents authenticate through the existing Reseller Portal LoginService.
 * No separate agent guard, model, or authentication application.
 */
class AgentLoginTest extends TestCase
{
    use UsesMysqlTestDatabase;

    private Role $ownerRole;

    private Role $agentRole;

    private Role $staffRole;

    private AgentService $agents;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        config(['cache.default' => 'array']);

        $this->ownerRole = $this->resellerRole('owner');
        $this->agentRole = $this->resellerRole('call-center-agent');
        $this->staffRole = $this->resellerRole('staff');
        $this->agents = app(AgentService::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_active_agent_can_log_in_with_phone_and_password(): void
    {
        $owner = $this->createOwner();
        $phone = '0777007001';
        $password = 'AgentLogin123!';

        $agent = $this->agents->create($owner, [
            'first_name' => 'Active',
            'last_name' => 'Agent',
            'phone' => $phone,
            'password' => $password,
            'agent_commission_per_order' => '50.00',
        ]);

        $response = $this->post(route('login.store'), [
            'identifier' => $phone,
            'password' => $password,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($agent);
        $this->assertTrue(Auth::check());
        $this->assertSame($agent->id, Auth::id());
        $this->assertSame((int) $owner->company_id, (int) Auth::user()->company_id);
        $this->assertTrue($this->agents->isCallCenterAgent(Auth::user(), (int) $owner->company_id));
    }

    public function test_agent_can_log_out_and_session_is_invalidated(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0777007002');

        $this->actingAs($agent)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0777007003');

        $this->from(route('login'))
            ->post(route('login.store'), [
                'identifier' => $agent->phone,
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_unknown_phone_is_rejected(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'identifier' => '0777007099',
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_suspended_agent_cannot_log_in(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0777007004');
        $agent->forceFill(['status' => UserStatus::SUSPENDED->value])->save();

        $this->from(route('login'))
            ->post(route('login.store'), [
                'identifier' => $agent->phone,
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_soft_deleted_agent_cannot_log_in(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0777007005');
        $phone = $agent->phone;
        $agent->delete();

        $this->from(route('login'))
            ->post(route('login.store'), [
                'identifier' => $phone,
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_rejected_agent_cannot_log_in(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0777007006');
        $agent->forceFill(['status' => UserStatus::REJECTED->value])->save();

        $this->from(route('login'))
            ->post(route('login.store'), [
                'identifier' => $agent->phone,
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
    }

    public function test_owner_is_not_treated_as_call_center_agent(): void
    {
        $owner = $this->createOwner();

        $this->post(route('login.store'), [
            'identifier' => $owner->phone,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($owner);
        $this->assertFalse($this->agents->isCallCenterAgent(Auth::user(), (int) $owner->company_id));
    }

    public function test_employee_with_wrong_role_is_not_treated_as_call_center_agent(): void
    {
        $owner = $this->createOwner();
        $staff = $this->createUser(
            (int) $owner->company_id,
            $this->staffRole,
            UserType::EMPLOYEE,
            'staff-login-'.Str::uuid().'@feeder.local',
            '0777007007',
        );

        $this->post(route('login.store'), [
            'identifier' => $staff->phone,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($staff);
        $this->assertFalse($this->agents->isCallCenterAgent(Auth::user(), (int) $owner->company_id));
    }

    public function test_non_reseller_employee_cannot_authenticate_into_reseller_portal(): void
    {
        $supplierPortal = Portal::query()->firstOrCreate(
            ['code' => PortalCode::SUPPLIER->value],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Supplier',
                'is_active' => true,
            ],
        );

        $suffix = Str::lower(Str::random(6));
        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'portal_id' => $supplierPortal->id,
            'name' => 'Supplier '.$suffix,
            'email' => 'supplier-'.$suffix.'@feeder.local',
            'phone' => '070'.random_int(1000000, 9999999),
            'registration_number' => 'SUP-'.$suffix,
            'status' => CompanyStatus::ACTIVE->value,
        ]);

        $supplierStaffRole = Role::query()
            ->where('slug', 'staff')
            ->whereNull('deleted_at')
            ->where('portal_id', $supplierPortal->id)
            ->first();

        $this->assertNotNull($supplierStaffRole, 'Missing SUPPLIER staff role.');

        $employee = $this->createUser(
            (int) $company->id,
            $supplierStaffRole,
            UserType::EMPLOYEE,
            'supplier-emp-'.$suffix.'@feeder.local',
            '0777007008',
        );

        $this->from(route('login'))
            ->post(route('login.store'), [
                'identifier' => $employee->phone,
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('identifier');

        $this->assertGuest();
        $this->assertFalse($this->agents->isCallCenterAgent($employee));
    }

    public function test_agent_without_permission_cannot_access_protected_pages(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0777007010');

        $this->actingAs($agent)
            ->get(route('products.index'))
            ->assertForbidden();

        $this->actingAs($agent)
            ->get(route('team.structure'))
            ->assertForbidden();

        $this->actingAs($agent)
            ->get(route('ui.call-center.agents.index'))
            ->assertForbidden();

        $this->actingAs($agent)
            ->get(route('ui.call-center.agents.create'))
            ->assertForbidden();
    }

    public function test_agent_with_products_view_can_access_products_and_without_team_permission_cannot(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0777007011');
        $this->grantPermissions($agent, ['products.view']);

        $this->actingAs($agent->fresh(['role', 'directPermissions']))
            ->get(route('products.index'))
            ->assertOk();

        $this->actingAs($agent)
            ->get(route('team.structure'))
            ->assertForbidden();
    }

    public function test_agent_with_team_structure_view_can_access_team_tree(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0777007012');
        $this->grantPermissions($agent, ['team.structure.view']);

        $this->actingAs($agent->fresh(['role', 'directPermissions']))
            ->get(route('team.structure'))
            ->assertOk();
    }

    public function test_agent_cannot_access_agent_management_routes(): void
    {
        $owner = $this->createOwner();
        $target = $this->createAgent($owner->company_id, '0777007013', 'Managed', 'Target');
        $actor = $this->createAgent($owner->company_id, '0777007014', 'Actor', 'Agent');

        foreach (AgentService::MANAGEMENT_PERMISSION_SLUGS as $slug) {
            $this->assertFalse($actor->fresh()->hasPermission($slug), "Agent must not have {$slug}");
        }

        $this->actingAs($actor)->get(route('ui.call-center.agents.index'))->assertForbidden();
        $this->actingAs($actor)->get(route('ui.call-center.agents.create'))->assertForbidden();
        $this->actingAs($actor)->get(route('ui.call-center.agents.show', $target->uuid))->assertForbidden();
        $this->actingAs($actor)->get(route('ui.call-center.agents.edit', $target->uuid))->assertForbidden();

        $this->actingAs($actor)
            ->post(route('ui.call-center.agents.store'), [
                'first_name' => 'Nope',
                'last_name' => 'Create',
                'phone' => '0777007098',
                'password' => 'SecretPass123!',
                'password_confirmation' => 'SecretPass123!',
                'agent_commission_per_order' => '10.00',
            ])
            ->assertForbidden();

        $this->actingAs($actor)
            ->post(route('ui.call-center.agents.deactivate', $target->uuid))
            ->assertForbidden();

        $this->actingAs($actor)
            ->post(route('ui.call-center.agents.commission.update', $target->uuid), [
                'agent_commission_per_order' => '99.00',
            ])
            ->assertForbidden();

        $this->actingAs($actor)
            ->post(route('ui.call-center.agents.permissions.update', $target->uuid), [
                'permissions' => ['products.view'],
            ])
            ->assertForbidden();
    }

    public function test_agent_cannot_self_manage_through_agent_management_routes(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0777007015');

        $this->actingAs($agent)
            ->get(route('ui.call-center.agents.show', $agent->uuid))
            ->assertForbidden();

        $this->actingAs($agent)
            ->get(route('ui.call-center.agents.edit', $agent->uuid))
            ->assertForbidden();

        $this->actingAs($agent)
            ->put(route('ui.call-center.agents.update', $agent->uuid), [
                'first_name' => 'Hacked',
                'last_name' => 'Name',
                'phone' => $agent->phone,
            ])
            ->assertForbidden();

        $this->actingAs($agent)
            ->post(route('ui.call-center.agents.deactivate', $agent->uuid))
            ->assertForbidden();

        $this->assertSame(UserStatus::ACTIVE, $agent->fresh()->status);
        $this->assertSame('Agent', $agent->fresh()->profile->first_name);
    }

    public function test_cross_company_agent_management_access_remains_rejected(): void
    {
        $ownerA = $this->createOwner();
        $ownerB = $this->createOwner();
        $agentA = $this->createAgent($ownerA->company_id, '0777007016', 'Company', 'A');
        $agentB = $this->createAgent($ownerB->company_id, '0777007017', 'Company', 'B');

        $this->actingAs($agentA)
            ->get(route('ui.call-center.agents.show', $agentB->uuid))
            ->assertForbidden();

        $this->actingAs($ownerA)
            ->get(route('ui.call-center.agents.show', $agentB->uuid))
            ->assertNotFound();

        $this->assertSame((int) $ownerA->company_id, (int) $agentA->company_id);
        $this->assertNotSame((int) $agentA->company_id, (int) $agentB->company_id);
    }

    public function test_client_controlled_auth_fields_are_ignored_on_login(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0777007018');
        $original = [
            'role_id' => (int) $agent->role_id,
            'user_type' => $agent->user_type,
            'company_id' => (int) $agent->company_id,
            'status' => $agent->status,
        ];

        $this->post(route('login.store'), [
            'identifier' => $agent->phone,
            'password' => 'password',
            'role_id' => $this->ownerRole->id,
            'user_type' => UserType::OWNER->value,
            'company_id' => 999999,
            'status' => UserStatus::SUSPENDED->value,
            'permissions' => [AgentService::PERMISSION_VIEW],
        ])->assertRedirect(route('dashboard'));

        $authenticated = Auth::user()->fresh();

        $this->assertAuthenticatedAs($agent);
        $this->assertSame($original['role_id'], (int) $authenticated->role_id);
        $this->assertSame($original['user_type'], $authenticated->user_type);
        $this->assertSame($original['company_id'], (int) $authenticated->company_id);
        $this->assertSame($original['status'], $authenticated->status);
        $this->assertFalse($authenticated->hasPermission(AgentService::PERMISSION_VIEW));
        $this->assertTrue($this->agents->isCallCenterAgent($authenticated, $original['company_id']));
    }

    public function test_existing_reseller_owner_login_still_works(): void
    {
        $owner = $this->createOwner();

        $this->post(route('login.store'), [
            'identifier' => $owner->phone,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($owner);
        $this->assertTrue($owner->fresh()->hasPermission(AgentService::PERMISSION_VIEW));
    }

    public function test_unauthenticated_access_to_protected_agent_pages_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0777007019');

        $this->get(route('ui.call-center.agents.index'))->assertRedirect();
        $this->get(route('ui.call-center.agents.show', $agent->uuid))->assertRedirect();
        $this->get(route('products.index'))->assertRedirect();
        $this->get(route('team.structure'))->assertRedirect();
        $this->get(route('dashboard'))->assertRedirect();
        $this->assertGuest();
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
                ->whereNull('deleted_at')
                ->whereHas('portal', fn ($query) => $query->where('code', PortalCode::RESELLER->value))
                ->pluck('id')
                ->all()
        );
    }

    private function createOwner(): User
    {
        $portal = Portal::query()->firstOrCreate(
            ['code' => PortalCode::RESELLER->value],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Reseller',
                'is_active' => true,
            ],
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
            'nic' => null,
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
