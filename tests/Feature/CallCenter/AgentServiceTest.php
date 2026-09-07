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
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class AgentServiceTest extends TestCase
{
    use UsesMysqlTestDatabase;

    private AgentService $agents;

    private Role $ownerRole;

    private Role $agentRole;

    private Role $staffRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();

        config(['cache.default' => 'array']);

        $this->agents = app(AgentService::class);
        $this->ownerRole = $this->resellerRole('owner');
        $this->agentRole = $this->resellerRole('call-center-agent');
        $this->staffRole = $this->resellerRole('staff');

        $this->assertNotEmpty(
            $this->ownerRole->permissions()
                ->whereIn('permissions.slug', AgentService::MANAGEMENT_PERMISSION_SLUGS)
                ->pluck('slug')
                ->all(),
            'Owner role must include Call Center Agent management permissions.'
        );
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_valid_agent_is_recognized_by_identity_invariant(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id);

        $this->assertTrue($this->agents->isCallCenterAgent($agent, (int) $owner->company_id));
    }

    public function test_ordinary_employee_is_rejected_as_agent(): void
    {
        $owner = $this->createOwner();
        $employee = $this->createUser(
            companyId: (int) $owner->company_id,
            role: $this->staffRole,
            userType: UserType::EMPLOYEE,
            email: 'staff-'.Str::uuid().'@feeder.local',
            phone: '077'.random_int(1000000, 9999999),
        );

        $this->assertFalse($this->agents->isCallCenterAgent($employee, (int) $owner->company_id));
    }

    public function test_owner_is_rejected_as_agent(): void
    {
        $owner = $this->createOwner();

        $this->assertFalse($this->agents->isCallCenterAgent($owner, (int) $owner->company_id));
    }

    public function test_wrong_role_is_rejected_as_agent(): void
    {
        $owner = $this->createOwner();
        $managerRole = $this->resellerRole('manager');
        $employee = $this->createUser(
            companyId: (int) $owner->company_id,
            role: $managerRole,
            userType: UserType::EMPLOYEE,
            email: 'mgr-'.Str::uuid().'@feeder.local',
            phone: '077'.random_int(1000000, 9999999),
        );

        $this->assertFalse($this->agents->isCallCenterAgent($employee, (int) $owner->company_id));
    }

    public function test_soft_deleted_agent_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id);
        $agentId = $agent->id;
        $agent->delete();

        $trashed = User::withTrashed()->findOrFail($agentId);

        $this->assertFalse($this->agents->isCallCenterAgent($trashed, (int) $owner->company_id));
        $this->assertFalse(
            $this->agents->listForCompany($owner)->where('users.id', $agentId)->exists()
        );
    }

    public function test_owner_can_list_and_find_own_company_agents_only(): void
    {
        $ownerA = $this->createOwner();
        $ownerB = $this->createOwner();
        $agentA = $this->createAgent($ownerA->company_id, phone: '0771111001');
        $agentB = $this->createAgent($ownerB->company_id, phone: '0771111002');

        $listed = $this->agents->listForCompany($ownerA)->get();

        $this->assertTrue($listed->contains('id', $agentA->id));
        $this->assertFalse($listed->contains('id', $agentB->id));

        $found = $this->agents->findForManagement($ownerA, $agentA->uuid);
        $this->assertSame($agentA->id, $found->id);

        $this->expectException(ModelNotFoundException::class);
        $this->agents->findForManagement($ownerA, $agentB->uuid);
    }

    public function test_cross_company_mutations_are_rejected(): void
    {
        $ownerA = $this->createOwner();
        $ownerB = $this->createOwner();
        $agentB = $this->createAgent($ownerB->company_id, phone: '0772222001');

        $this->expectException(ModelNotFoundException::class);
        $this->agents->update($ownerA, $agentB, ['first_name' => 'Hacker']);
    }

    public function test_cross_company_activate_deactivate_commission_and_permissions_are_rejected(): void
    {
        $ownerA = $this->createOwner();
        $ownerB = $this->createOwner();
        $agentB = $this->createAgent($ownerB->company_id, phone: '0772222002');

        foreach ([
            fn () => $this->agents->activate($ownerA, $agentB),
            fn () => $this->agents->deactivate($ownerA, $agentB),
            fn () => $this->agents->updateCommission($ownerA, $agentB, '50.00'),
            fn () => $this->agents->syncPermissions($ownerA, $agentB, []),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Expected ModelNotFoundException for cross-company mutation.');
            } catch (ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_create_agent_sets_identity_profile_commission_and_hashed_password(): void
    {
        $owner = $this->createOwner();
        $phone = '0773333001';

        $agent = $this->agents->create($owner, [
            'first_name' => 'Amali',
            'last_name' => 'Perera',
            'phone' => $phone,
            'password' => 'SecretPass123!',
            'nic' => null,
            'agent_commission_per_order' => '75.50',
        ]);

        $this->assertSame(UserType::EMPLOYEE->value, $agent->user_type);
        $this->assertSame((int) $owner->company_id, (int) $agent->company_id);
        $this->assertSame($this->agentRole->id, (int) $agent->role_id);
        $this->assertSame(UserStatus::ACTIVE, $agent->status);
        $this->assertSame($phone, $agent->phone);
        $this->assertSame(sprintf('%s@reseller.local', $phone), $agent->email);
        $this->assertTrue(Hash::check('SecretPass123!', $agent->password));
        $this->assertNotSame('SecretPass123!', $agent->password);

        $this->assertNotNull($agent->profile);
        $this->assertSame('Amali', $agent->profile->first_name);
        $this->assertSame('Perera', $agent->profile->last_name);
        $this->assertNull($agent->profile->nic);
        $this->assertSame('75.50', (string) $agent->profile->agent_commission_per_order);
    }

    public function test_create_rejects_negative_commission(): void
    {
        $owner = $this->createOwner();

        $this->expectException(ValidationException::class);

        $this->agents->create($owner, [
            'first_name' => 'Neg',
            'last_name' => 'Commission',
            'phone' => '0773333002',
            'password' => 'SecretPass123!',
            'agent_commission_per_order' => '-1',
        ]);
    }

    public function test_update_changes_profile_fields_without_mutating_identity_or_status(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, phone: '0774444001', commission: '40.00');
        $originalRoleId = $agent->role_id;
        $originalCompanyId = $agent->company_id;
        $originalType = $agent->user_type;
        $originalStatus = $agent->status;

        $updated = $this->agents->update($owner, $agent, [
            'first_name' => 'Nimal',
            'last_name' => 'Silva',
            'phone' => '0774444009',
            'nic' => '199012345678',
            'password' => 'NewSecret999!',
            'company_id' => 999999,
            'user_type' => UserType::OWNER->value,
            'role_id' => $this->ownerRole->id,
            'status' => UserStatus::SUSPENDED->value,
            'agent_commission_per_order' => '999.00',
        ]);

        $this->assertSame('Nimal', $updated->profile->first_name);
        $this->assertSame('Silva', $updated->profile->last_name);
        $this->assertSame('0774444009', $updated->phone);
        $this->assertSame('0774444009@reseller.local', $updated->email);
        $this->assertSame('199012345678', $updated->profile->nic);
        $this->assertTrue(Hash::check('NewSecret999!', $updated->password));

        $this->assertSame($originalCompanyId, $updated->company_id);
        $this->assertSame($originalType, $updated->user_type);
        $this->assertSame($originalRoleId, $updated->role_id);
        $this->assertSame($originalStatus, $updated->status);
        $this->assertSame('40.00', (string) $updated->profile->agent_commission_per_order);
    }

    public function test_activate_and_deactivate_lifecycle(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, phone: '0775555001');

        $deactivated = $this->agents->deactivate($owner, $agent);
        $this->assertSame(UserStatus::SUSPENDED, $deactivated->status);

        $activated = $this->agents->activate($owner, $deactivated);
        $this->assertSame(UserStatus::ACTIVE, $activated->status);
    }

    public function test_update_commission_stores_current_rate_without_ledger_tables(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, phone: '0776666001', commission: '25.00');

        $updated = $this->agents->updateCommission($owner, $agent, '100.00');

        $this->assertSame('100.00', (string) $updated->profile->agent_commission_per_order);
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasTable('agent_commission_ledgers'),
            'No commission ledger table should exist in O1.4-C.'
        );
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('agent_earnings'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('call_center_agents'));
    }

    public function test_negative_commission_update_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, phone: '0776666002');

        $this->expectException(ValidationException::class);
        $this->agents->updateCommission($owner, $agent, '-5');
    }

    public function test_permission_sync_persists_allow_and_deny_via_user_permission_service(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, phone: '0777777001');

        $productsView = $this->resellerPermission('products.view');
        $dashboardView = $this->resellerPermission('dashboard.view');

        $this->agents->syncPermissions($owner, $agent, [
            'products.view' => true,
            'dashboard.view' => false,
        ]);

        $this->assertOverrideState($agent, $productsView->id, true);
        $this->assertOverrideState($agent, $dashboardView->id, false);

        $fresh = $agent->fresh();
        $this->assertTrue($fresh->hasPermission('products.view'));
        $this->assertFalse($fresh->hasPermission('dashboard.view'));
    }

    public function test_management_permissions_cannot_be_assigned_to_agents(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, phone: '0777777002');

        $this->expectException(ValidationException::class);

        $this->agents->syncPermissions($owner, $agent, [
            AgentService::PERMISSION_VIEW => true,
        ]);
    }

    public function test_self_management_is_rejected_for_mutating_operations(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, phone: '0778888001');

        // Grant agent management permissions so the failure is self-targeting, not missing permission.
        app(UserPermissionService::class)->syncAllowed(
            $agent,
            Permission::query()
                ->whereIn('slug', AgentService::MANAGEMENT_PERMISSION_SLUGS)
                ->whereHas('portal', fn ($q) => $q->where('code', PortalCode::RESELLER->value))
                ->pluck('id')
                ->all()
        );

        $agent = $agent->fresh(['role', 'company']);

        foreach ([
            fn () => $this->agents->update($agent, $agent, ['first_name' => 'Self']),
            fn () => $this->agents->activate($agent, $agent),
            fn () => $this->agents->deactivate($agent, $agent),
            fn () => $this->agents->updateCommission($agent, $agent, '10.00'),
            fn () => $this->agents->syncPermissions($agent, $agent, []),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Expected AuthorizationException for self-management.');
            } catch (AuthorizationException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_missing_permission_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agentWithoutPerms = $this->createAgent($owner->company_id, phone: '0778888002');

        $this->expectException(AuthorizationException::class);
        $this->agents->listForCompany($agentWithoutPerms);
    }

    public function test_active_agent_can_authenticate_and_suspended_cannot(): void
    {
        $owner = $this->createOwner();
        $phone = '0779999001';
        $password = 'AgentLogin123!';

        $agent = $this->agents->create($owner, [
            'first_name' => 'Login',
            'last_name' => 'Agent',
            'phone' => $phone,
            'password' => $password,
            'agent_commission_per_order' => '50.00',
        ]);

        $loginService = app(LoginService::class);
        $this->startSession();

        $activeRequest = LoginRequest::create('/login', 'POST', [
            'identifier' => $phone,
            'password' => $password,
        ]);
        $activeRequest->setContainer(app());
        $activeRequest->setRedirector(app('redirect'));
        $activeRequest->setLaravelSession(app('session.store'));
        app()->instance('request', $activeRequest);

        $authenticated = $loginService->login($activeRequest, PortalCode::RESELLER);
        $this->assertSame($agent->id, $authenticated->id);

        auth()->logout();

        $this->agents->deactivate($owner, $agent);

        $suspendedRequest = LoginRequest::create('/login', 'POST', [
            'identifier' => $phone,
            'password' => $password,
        ]);
        $suspendedRequest->setContainer(app());
        $suspendedRequest->setRedirector(app('redirect'));
        $suspendedRequest->setLaravelSession(app('session.store'));
        app()->instance('request', $suspendedRequest);

        try {
            $loginService->login($suspendedRequest, PortalCode::RESELLER);
            $this->fail('Suspended agent must not authenticate.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('identifier', $exception->errors());
        }
    }

    public function test_owner_cannot_manage_non_agent_employee_in_same_company(): void
    {
        $owner = $this->createOwner();
        $staff = $this->createUser(
            companyId: (int) $owner->company_id,
            role: $this->staffRole,
            userType: UserType::EMPLOYEE,
            email: 'plain-staff-'.Str::uuid().'@feeder.local',
            phone: '0770000111',
        );

        $this->expectException(ModelNotFoundException::class);
        $this->agents->update($owner, $staff, ['first_name' => 'Nope']);
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
            companyId: $company->id,
            role: $this->ownerRole,
            userType: UserType::OWNER,
            email: 'owner-'.$suffix.'@feeder.local',
            phone: $phone,
        );

        $company->forceFill(['owner_user_id' => $owner->id])->save();

        return $owner->fresh(['company', 'role']);
    }

    private function createAgent(
        int|string $companyId,
        ?string $phone = null,
        string $commission = '50.00',
    ): User {
        $phone ??= '077'.random_int(1000000, 9999999);

        $agent = $this->createUser(
            companyId: (int) $companyId,
            role: $this->agentRole,
            userType: UserType::EMPLOYEE,
            email: sprintf('%s@reseller.local', $phone),
            phone: $phone,
        );

        UserProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $agent->id,
            'first_name' => 'Agent',
            'last_name' => 'User',
            'nic' => null,
            'agent_commission_per_order' => $commission,
        ]);

        return $agent->fresh(['profile', 'role', 'company']);
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

    private function resellerPermission(string $slug): Permission
    {
        $permission = Permission::query()
            ->where('slug', $slug)
            ->whereNull('deleted_at')
            ->whereHas('portal', fn ($query) => $query->where('code', PortalCode::RESELLER->value))
            ->first();

        $this->assertNotNull($permission, "Missing RESELLER permission: {$slug}");

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
}
