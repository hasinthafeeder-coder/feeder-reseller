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

class AgentEditTest extends TestCase
{
    use UsesMysqlTestDatabase;

    private Role $ownerRole;

    private Role $agentRole;

    private Role $staffRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        config(['cache.default' => 'array']);

        $this->ownerRole = $this->resellerRole('owner');
        $this->agentRole = $this->resellerRole('call-center-agent');
        $this->staffRole = $this->resellerRole('staff');
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_owner_can_view_edit_form_with_real_agent_data(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0773003001', 'Amali', 'Perera', '100.00', '199012345678');

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.edit', $agent->uuid))
            ->assertOk()
            ->assertSee('Amali')
            ->assertSee('Perera')
            ->assertSee('0773003001')
            ->assertSee('199012345678')
            ->assertDontSee('AgentUiPreviewData')
            ->assertDontSee('amali-perera');
    }

    public function test_owner_can_update_agent_profile_fields(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0773003010', 'Old', 'Name', '75.00');

        $response = $this->actingAs($owner)->put(route('ui.call-center.agents.update', $agent->uuid), [
            'first_name' => 'Nimal',
            'last_name' => 'Silva',
            'phone' => '0773003019',
            'nic' => '199012345679',
        ]);

        $response->assertRedirect(route('ui.call-center.agents.show', $agent->uuid));
        $response->assertSessionHas('success', 'Call Center Agent updated successfully.');

        $agent->refresh()->load('profile');

        $this->assertSame('Nimal', $agent->profile->first_name);
        $this->assertSame('Silva', $agent->profile->last_name);
        $this->assertSame('0773003019', $agent->phone);
        $this->assertSame('199012345679', $agent->profile->nic);
        $this->assertSame('0773003019@reseller.local', $agent->email);
    }

    public function test_blank_password_keeps_existing_password(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0773003020');
        $originalHash = $agent->password;

        $this->actingAs($owner)->put(route('ui.call-center.agents.update', $agent->uuid), [
            'first_name' => 'Keep',
            'last_name' => 'Password',
            'phone' => '0773003020',
            'password' => '',
            'password_confirmation' => '',
        ])->assertRedirect();

        $agent->refresh();

        $this->assertSame($originalHash, $agent->password);
        $this->assertTrue(Hash::check('password', $agent->password));
    }

    public function test_valid_new_password_is_hashed_and_stored(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0773003021');
        $newPassword = 'NewSecretPass123!';

        $this->actingAs($owner)->put(route('ui.call-center.agents.update', $agent->uuid), [
            'first_name' => 'Agent',
            'last_name' => 'User',
            'phone' => '0773003021',
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect();

        $agent->refresh();

        $this->assertTrue(Hash::check($newPassword, $agent->password));
        $this->assertNotSame($newPassword, $agent->password);
        $this->assertFalse(Hash::check('password', $agent->password));
    }

    public function test_password_confirmation_is_required_when_password_supplied(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0773003022');

        $this->actingAs($owner)
            ->from(route('ui.call-center.agents.edit', $agent->uuid))
            ->put(route('ui.call-center.agents.update', $agent->uuid), [
                'first_name' => 'Agent',
                'last_name' => 'User',
                'phone' => '0773003022',
                'password' => 'NewSecretPass123!',
                'password_confirmation' => 'DifferentPass123!',
            ])
            ->assertRedirect(route('ui.call-center.agents.edit', $agent->uuid))
            ->assertSessionHasErrors('password');

        $oldInput = session()->get('_old_input', []);
        $this->assertArrayNotHasKey('password', $oldInput);
        $this->assertArrayNotHasKey('password_confirmation', $oldInput);
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        $owner = $this->createOwner();
        $this->createAgent($owner->company_id, '0773003030');
        $target = $this->createAgent($owner->company_id, '0773003031');

        $this->actingAs($owner)->put(route('ui.call-center.agents.update', $target->uuid), [
            'first_name' => 'Agent',
            'last_name' => 'User',
            'phone' => '0773003030',
        ])->assertSessionHasErrors('phone');

        $target->refresh();
        $this->assertSame('0773003031', $target->phone);
    }

    public function test_current_agent_own_phone_is_accepted(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0773003032', 'Same', 'Phone');

        $this->actingAs($owner)->put(route('ui.call-center.agents.update', $agent->uuid), [
            'first_name' => 'Updated',
            'last_name' => 'Phone',
            'phone' => '0773003032',
        ])->assertRedirect();

        $agent->refresh()->load('profile');
        $this->assertSame('Updated', $agent->profile->first_name);
        $this->assertSame('0773003032', $agent->phone);
    }

    public function test_valid_nic_is_accepted_and_blank_nic_clears_value(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0773003040', nic: '901234567V');

        $this->actingAs($owner)->put(route('ui.call-center.agents.update', $agent->uuid), [
            'first_name' => 'Agent',
            'last_name' => 'User',
            'phone' => '0773003040',
            'nic' => '199012345680',
        ])->assertRedirect();

        $this->assertSame('199012345680', $agent->fresh()->profile->nic);

        $this->actingAs($owner)->put(route('ui.call-center.agents.update', $agent->uuid), [
            'first_name' => 'Agent',
            'last_name' => 'User',
            'phone' => '0773003040',
            'nic' => '',
        ])->assertRedirect();

        $this->assertNull($agent->fresh()->profile->nic);
    }

    public function test_duplicate_nic_is_rejected(): void
    {
        $owner = $this->createOwner();
        $this->createAgent($owner->company_id, '0773003041', nic: '901234568V');
        $target = $this->createAgent($owner->company_id, '0773003042', nic: null);

        $this->actingAs($owner)->put(route('ui.call-center.agents.update', $target->uuid), [
            'first_name' => 'Agent',
            'last_name' => 'User',
            'phone' => '0773003042',
            'nic' => '901234568V',
        ])->assertSessionHasErrors('nic');

        $this->assertNull($target->fresh()->profile->nic);
    }

    public function test_current_agent_own_nic_is_accepted(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0773003043', nic: '901234569V');

        $this->actingAs($owner)->put(route('ui.call-center.agents.update', $agent->uuid), [
            'first_name' => 'Same',
            'last_name' => 'Nic',
            'phone' => '0773003043',
            'nic' => '901234569V',
        ])->assertRedirect();

        $this->assertSame('901234569V', $agent->fresh()->profile->nic);
    }

    public function test_unauthenticated_user_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0773003050');

        $this->put(route('ui.call-center.agents.update', $agent->uuid), [
            'first_name' => 'Nope',
            'last_name' => 'User',
            'phone' => '0773003050',
        ])->assertRedirect();

        $this->assertGuest();
    }

    public function test_actor_without_update_permission_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0773003051');
        $staff = $this->createUser(
            (int) $owner->company_id,
            $this->staffRole,
            UserType::EMPLOYEE,
            'staff-edit-'.Str::uuid().'@feeder.local',
            '0773003059',
        );

        $this->actingAs($staff)->put(route('ui.call-center.agents.update', $agent->uuid), [
            'first_name' => 'Blocked',
            'last_name' => 'User',
            'phone' => '0773003051',
        ])->assertForbidden();

        $this->assertSame('Agent', $agent->fresh()->profile->first_name);
    }

    public function test_self_edit_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0773003060');

        app(UserPermissionService::class)->syncAllowed(
            $agent,
            Permission::query()
                ->whereIn('slug', [
                    AgentService::PERMISSION_VIEW,
                    AgentService::PERMISSION_UPDATE,
                ])
                ->whereHas('portal', fn ($q) => $q->where('code', PortalCode::RESELLER->value))
                ->pluck('id')
                ->all()
        );

        $agent = $agent->fresh(['role', 'company', 'profile']);

        $this->actingAs($agent)->put(route('ui.call-center.agents.update', $agent->uuid), [
            'first_name' => 'Self',
            'last_name' => 'Edit',
            'phone' => '0773003060',
        ])->assertForbidden();

        $this->assertSame('Agent', $agent->fresh()->profile->first_name);
    }

    public function test_cross_company_target_is_rejected(): void
    {
        $ownerA = $this->createOwner();
        $ownerB = $this->createOwner();
        $foreign = $this->createAgent($ownerB->company_id, '0773003070', 'Foreign', 'Agent');

        $this->actingAs($ownerA)->put(route('ui.call-center.agents.update', $foreign->uuid), [
            'first_name' => 'Hacked',
            'last_name' => 'Name',
            'phone' => '0773003070',
        ])->assertNotFound();

        $this->assertSame('Foreign', $foreign->fresh()->profile->first_name);
    }

    public function test_wrong_user_type_is_rejected(): void
    {
        $owner = $this->createOwner();
        $staff = $this->createUser(
            (int) $owner->company_id,
            $this->staffRole,
            UserType::EMPLOYEE,
            'staff-type-'.Str::uuid().'@feeder.local',
            '0773003080',
        );
        UserProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $staff->id,
            'first_name' => 'Staff',
            'last_name' => 'Member',
            'nic' => null,
            'agent_commission_per_order' => '10.00',
        ]);

        $this->actingAs($owner)->put(route('ui.call-center.agents.update', $staff->uuid), [
            'first_name' => 'Nope',
            'last_name' => 'Staff',
            'phone' => '0773003080',
        ])->assertNotFound();
    }

    public function test_wrong_role_is_rejected(): void
    {
        $owner = $this->createOwner();
        $otherOwner = $this->createUser(
            (int) $owner->company_id,
            $this->ownerRole,
            UserType::OWNER,
            'owner-role-'.Str::uuid().'@feeder.local',
            '0773003081',
        );
        UserProfile::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $otherOwner->id,
            'first_name' => 'Other',
            'last_name' => 'Owner',
            'nic' => null,
            'agent_commission_per_order' => null,
        ]);

        $this->actingAs($owner)->put(route('ui.call-center.agents.update', $otherOwner->uuid), [
            'first_name' => 'Nope',
            'last_name' => 'Owner',
            'phone' => '0773003081',
        ])->assertNotFound();
    }

    public function test_soft_deleted_target_is_rejected(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0773003090');
        $uuid = $agent->uuid;
        $agent->delete();

        $this->actingAs($owner)->put(route('ui.call-center.agents.update', $uuid), [
            'first_name' => 'Gone',
            'last_name' => 'Agent',
            'phone' => '0773003090',
        ])->assertNotFound();
    }

    public function test_protected_fields_cannot_be_changed_via_edit_request(): void
    {
        $ownerA = $this->createOwner();
        $ownerB = $this->createOwner();
        $agent = $this->createAgent($ownerA->company_id, '0773003100', 'Safe', 'Agent', '55.00');

        $original = [
            'company_id' => (int) $agent->company_id,
            'user_type' => $agent->user_type,
            'role_id' => (int) $agent->role_id,
            'status' => $agent->status,
            'email' => $agent->email,
            'commission' => (string) $agent->profile->agent_commission_per_order,
        ];

        $permissionCountBefore = DB::table('user_permissions')->where('user_id', $agent->id)->count();

        $this->actingAs($ownerA)->put(route('ui.call-center.agents.update', $agent->uuid), [
            'first_name' => 'Legit',
            'last_name' => 'Update',
            'phone' => '0773003100',
            'nic' => '199012345681',
            'company_id' => $ownerB->company_id,
            'user_type' => UserType::OWNER->value,
            'role_id' => $this->ownerRole->id,
            'status' => 'inactive',
            'agent_commission_per_order' => '9999.00',
            'permissions' => [
                AgentService::PERMISSION_CREATE,
                'view_orders',
            ],
            'email' => 'attacker@example.com',
            'uuid' => (string) Str::uuid(),
        ])->assertRedirect();

        $agent->refresh()->load(['profile', 'role']);

        $this->assertSame('Legit', $agent->profile->first_name);
        $this->assertSame('Update', $agent->profile->last_name);
        $this->assertSame('199012345681', $agent->profile->nic);

        $this->assertSame($original['company_id'], (int) $agent->company_id);
        $this->assertSame($original['user_type'], $agent->user_type);
        $this->assertSame($original['role_id'], (int) $agent->role_id);
        $this->assertSame($original['status'], $agent->status);
        $this->assertSame($original['email'], $agent->email);
        $this->assertSame($original['commission'], (string) $agent->profile->agent_commission_per_order);
        $this->assertSame(UserType::EMPLOYEE->value, $agent->user_type);
        $this->assertSame(AgentService::ROLE_SLUG, $agent->role->slug);
        $this->assertSame($permissionCountBefore, DB::table('user_permissions')->where('user_id', $agent->id)->count());
    }

    public function test_identity_invariants_remain_after_update(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0773003110', commission: '42.50');
        $agent->forceFill(['status' => UserStatus::SUSPENDED->value])->save();

        $this->actingAs($owner)->put(route('ui.call-center.agents.update', $agent->uuid), [
            'first_name' => 'Invariant',
            'last_name' => 'Check',
            'phone' => '0773003111',
            'status' => 'active',
            'agent_commission_per_order' => '1.00',
        ])->assertRedirect();

        $agent->refresh()->load(['profile', 'role']);

        $this->assertSame(UserType::EMPLOYEE->value, $agent->user_type);
        $this->assertSame(AgentService::ROLE_SLUG, $agent->role->slug);
        $this->assertSame((int) $owner->company_id, (int) $agent->company_id);
        $this->assertSame(UserStatus::SUSPENDED, $agent->status);
        $this->assertSame('42.50', (string) $agent->profile->agent_commission_per_order);
        $this->assertSame('0773003111@reseller.local', $agent->email);
    }

    public function test_synthetic_email_syncs_when_phone_changes(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0773003120');

        $this->assertSame('0773003120@reseller.local', $agent->email);

        $this->actingAs($owner)->put(route('ui.call-center.agents.update', $agent->uuid), [
            'first_name' => 'Email',
            'last_name' => 'Sync',
            'phone' => '0773003129',
        ])->assertRedirect();

        $agent->refresh();

        $this->assertSame('0773003129', $agent->phone);
        $this->assertSame('0773003129@reseller.local', $agent->email);
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
