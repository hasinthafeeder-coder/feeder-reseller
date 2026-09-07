<?php

namespace Tests\Feature\CallCenter;

use App\Services\CallCenter\AgentService;
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

class AgentCreateTest extends TestCase
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

    public function test_owner_can_create_agent_with_identity_profile_and_hashed_password(): void
    {
        $owner = $this->createOwner();
        $phone = '0772002001';
        $password = 'SecretPass123!';

        $response = $this->actingAs($owner)->post(route('ui.call-center.agents.store'), [
            'first_name' => 'Amali',
            'last_name' => 'Perera',
            'phone' => $phone,
            'password' => $password,
            'password_confirmation' => $password,
            'agent_commission_per_order' => '75.00',
            'permissions' => ['view_orders', 'confirm_orders'],
        ]);

        $agent = User::query()->where('phone', $phone)->first();

        $this->assertNotNull($agent);
        $response->assertRedirect(route('ui.call-center.agents.show', $agent->uuid));
        $response->assertSessionHas('success', 'Call Center Agent created successfully.');

        $this->assertSame(UserType::EMPLOYEE->value, $agent->user_type);
        $this->assertSame((int) $owner->company_id, (int) $agent->company_id);
        $this->assertSame($this->agentRole->id, (int) $agent->role_id);
        $this->assertSame(UserStatus::ACTIVE, $agent->status);
        $this->assertSame(sprintf('%s@reseller.local', $phone), $agent->email);
        $this->assertTrue(Hash::check($password, $agent->password));
        $this->assertNotSame($password, $agent->password);

        $this->assertNotNull($agent->profile);
        $this->assertSame('Amali', $agent->profile->first_name);
        $this->assertSame('Perera', $agent->profile->last_name);
        $this->assertNull($agent->profile->nic);
        $this->assertSame('75.00', (string) $agent->profile->agent_commission_per_order);

        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $agent->id)->count());
    }

    public function test_optional_nic_is_persisted_when_provided(): void
    {
        $owner = $this->createOwner();
        $phone = '0772002002';
        $nic = '199012345678';

        $this->actingAs($owner)->post(route('ui.call-center.agents.store'), $this->validPayload([
            'phone' => $phone,
            'nic' => $nic,
        ]))->assertRedirect();

        $agent = User::query()->where('phone', $phone)->firstOrFail();

        $this->assertSame($nic, $agent->profile->nic);
    }

    public function test_agent_can_be_created_without_nic(): void
    {
        $owner = $this->createOwner();
        $phone = '0772002003';

        $this->actingAs($owner)->post(route('ui.call-center.agents.store'), $this->validPayload([
            'phone' => $phone,
            'nic' => '',
        ]))->assertRedirect();

        $agent = User::query()->where('phone', $phone)->firstOrFail();
        $this->assertNull($agent->profile->nic);
    }

    public function test_commission_precision_is_stored(): void
    {
        $owner = $this->createOwner();

        foreach ([
            ['0752002101', '75', '75.00'],
            ['0752002102', '100', '100.00'],
            ['0752002103', '150.50', '150.50'],
        ] as [$phone, $input, $expected]) {
            $this->actingAs($owner)->post(route('ui.call-center.agents.store'), $this->validPayload([
                'phone' => $phone,
                'agent_commission_per_order' => $input,
            ]))->assertRedirect();

            $agent = User::query()->where('phone', $phone)->firstOrFail();
            $this->assertSame($expected, (string) $agent->profile->agent_commission_per_order);
        }
    }

    public function test_duplicate_nic_is_rejected(): void
    {
        $owner = $this->createOwner();
        $existing = $this->createAgent($owner->company_id, '0772002010');
        $existing->profile->forceFill(['nic' => '901234567V'])->save();

        $this->actingAs($owner)->post(route('ui.call-center.agents.store'), $this->validPayload([
            'phone' => '0772002011',
            'nic' => '901234567V',
        ]))->assertSessionHasErrors('nic');

        $this->assertNull(User::query()->where('phone', '0772002011')->first());
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        $owner = $this->createOwner();
        $this->createAgent($owner->company_id, '0772002020');

        $this->actingAs($owner)->post(route('ui.call-center.agents.store'), $this->validPayload([
            'phone' => '0772002020',
        ]))->assertSessionHasErrors('phone');
    }

    public function test_missing_commission_is_rejected(): void
    {
        $owner = $this->createOwner();

        $payload = $this->validPayload(['phone' => '0772002030']);
        unset($payload['agent_commission_per_order']);

        $this->actingAs($owner)
            ->from(route('ui.call-center.agents.create'))
            ->post(route('ui.call-center.agents.store'), $payload)
            ->assertRedirect(route('ui.call-center.agents.create'))
            ->assertSessionHasErrors('agent_commission_per_order');
    }

    public function test_negative_commission_is_rejected(): void
    {
        $owner = $this->createOwner();

        $this->actingAs($owner)->post(route('ui.call-center.agents.store'), $this->validPayload([
            'phone' => '0772002031',
            'agent_commission_per_order' => '-1',
        ]))->assertSessionHasErrors('agent_commission_per_order');
    }

    public function test_password_confirmation_mismatch_is_rejected(): void
    {
        $owner = $this->createOwner();

        $this->actingAs($owner)->post(route('ui.call-center.agents.store'), $this->validPayload([
            'phone' => '0772002032',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'DifferentPass123!',
        ]))->assertSessionHasErrors('password');
    }

    public function test_staff_without_create_permission_gets_403(): void
    {
        $owner = $this->createOwner();
        $staff = $this->createUser(
            (int) $owner->company_id,
            $this->staffRole,
            UserType::EMPLOYEE,
            'staff-'.Str::uuid().'@feeder.local',
            '0772002099',
        );

        $this->actingAs($staff)->post(route('ui.call-center.agents.store'), $this->validPayload([
            'phone' => '0772002040',
        ]))->assertForbidden();

        $this->assertNull(User::query()->where('phone', '0772002040')->first());
    }

    public function test_call_center_agent_cannot_create_another_agent(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0772002050');

        $this->actingAs($agent)->post(route('ui.call-center.agents.store'), $this->validPayload([
            'phone' => '0772002051',
        ]))->assertForbidden();

        $this->assertNull(User::query()->where('phone', '0772002051')->first());
    }

    public function test_submitted_company_id_is_ignored_and_actor_company_is_used(): void
    {
        $ownerA = $this->createOwner();
        $ownerB = $this->createOwner();
        $phone = '0772002060';

        $this->actingAs($ownerA)->post(route('ui.call-center.agents.store'), $this->validPayload([
            'phone' => $phone,
            'company_id' => $ownerB->company_id,
        ]))->assertRedirect();

        $agent = User::query()->where('phone', $phone)->firstOrFail();

        $this->assertSame((int) $ownerA->company_id, (int) $agent->company_id);
        $this->assertNotSame((int) $ownerB->company_id, (int) $agent->company_id);
    }

    public function test_submitted_role_id_cannot_create_owner(): void
    {
        $owner = $this->createOwner();
        $phone = '0772002061';

        $this->actingAs($owner)->post(route('ui.call-center.agents.store'), $this->validPayload([
            'phone' => $phone,
            'role_id' => $this->ownerRole->id,
            'user_type' => UserType::OWNER->value,
            'status' => 'inactive',
        ]))->assertRedirect();

        $agent = User::query()->where('phone', $phone)->with('role')->firstOrFail();

        $this->assertSame($this->agentRole->id, (int) $agent->role_id);
        $this->assertSame(AgentService::ROLE_SLUG, $agent->role->slug);
        $this->assertSame(UserType::EMPLOYEE->value, $agent->user_type);
        $this->assertSame(UserStatus::ACTIVE, $agent->status);
    }

    public function test_management_permissions_cannot_be_assigned_on_create(): void
    {
        $owner = $this->createOwner();
        $phone = '0772002070';

        $this->actingAs($owner)->post(route('ui.call-center.agents.store'), $this->validPayload([
            'phone' => $phone,
            'permissions' => [
                AgentService::PERMISSION_CREATE,
                AgentService::PERMISSION_VIEW,
            ],
        ]))->assertSessionHasErrors('permissions');

        $this->assertNull(User::query()->where('phone', $phone)->first());
    }

    public function test_preview_only_permission_keys_are_not_persisted(): void
    {
        $owner = $this->createOwner();
        $phone = '0772002071';

        $this->actingAs($owner)->post(route('ui.call-center.agents.store'), $this->validPayload([
            'phone' => $phone,
            'permissions' => [
                'view_orders',
                'confirm_orders',
                'manage_call_attempts',
            ],
        ]))->assertRedirect();

        $agent = User::query()->where('phone', $phone)->firstOrFail();

        $this->assertSame(0, DB::table('user_permissions')->where('user_id', $agent->id)->count());
        $this->assertNull(
            Permission::query()->where('slug', 'view_orders')->first(),
            'Preview permission keys must not exist as real permission records.'
        );
    }

    public function test_create_transaction_rolls_back_when_profile_save_fails(): void
    {
        $owner = $this->createOwner();
        $phone = '0772002080';

        UserProfile::creating(function () {
            throw new \RuntimeException('Forced profile failure for rollback test.');
        });

        try {
            $response = $this->actingAs($owner)->post(route('ui.call-center.agents.store'), $this->validPayload([
                'phone' => $phone,
            ]));

            $response->assertStatus(500);
        } finally {
            UserProfile::flushEventListeners();
        }

        $this->assertNull(User::query()->where('phone', $phone)->first());
        $this->assertNull(User::withTrashed()->where('phone', $phone)->first());
    }

    public function test_validation_failure_preserves_old_input_except_passwords(): void
    {
        $owner = $this->createOwner();

        $response = $this->actingAs($owner)
            ->from(route('ui.call-center.agents.create'))
            ->post(route('ui.call-center.agents.store'), [
                'first_name' => 'Kasuni',
                'last_name' => 'Fernando',
                'phone' => '0772002090',
                'password' => 'SecretPass123!',
                'password_confirmation' => 'MismatchPass123!',
                'agent_commission_per_order' => '125.50',
                'permissions' => ['view_orders'],
            ]);

        $response->assertRedirect(route('ui.call-center.agents.create'));
        $response->assertSessionHasErrors('password');
        $response->assertSessionHasInput([
            'first_name' => 'Kasuni',
            'last_name' => 'Fernando',
            'phone' => '0772002090',
            'agent_commission_per_order' => '125.50',
        ]);

        $oldInput = session()->get('_old_input', []);
        $this->assertArrayNotHasKey('password', $oldInput);
        $this->assertArrayNotHasKey('password_confirmation', $oldInput);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Amali',
            'last_name' => 'Perera',
            'phone' => '0772999000',
            'password' => 'SecretPass123!',
            'password_confirmation' => 'SecretPass123!',
            'agent_commission_per_order' => '100.00',
        ], $overrides);
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

    private function createAgent(int|string $companyId, string $phone): User
    {
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
            'first_name' => 'Agent',
            'last_name' => 'User',
            'nic' => null,
            'agent_commission_per_order' => '50.00',
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
