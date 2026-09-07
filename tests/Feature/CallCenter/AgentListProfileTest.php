<?php

namespace Tests\Feature\CallCenter;

use App\Services\CallCenter\AgentService;
use Feeder\Core\Authorization\Services\MenuService;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\Role;
use Feeder\Core\Models\User;
use Feeder\Core\Models\UserProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class AgentListProfileTest extends TestCase
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

    public function test_owner_can_view_own_company_agents_on_list(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0771001001', 'Amali', 'Perera', '100.00');

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.index'))
            ->assertOk()
            ->assertSee('Amali Perera')
            ->assertSee('0771001001')
            ->assertSee('LKR 100.00')
            ->assertSee('cca.amali.perera')
            ->assertDontSee('2554')
            ->assertDontSee('AgentUiPreviewData');
    }

    public function test_list_excludes_other_company_and_soft_deleted_agents(): void
    {
        $ownerA = $this->createOwner();
        $ownerB = $this->createOwner();

        $ownAgent = $this->createAgent($ownerA->company_id, '0771001002', 'Own', 'Agent');
        $foreignAgent = $this->createAgent($ownerB->company_id, '0771001003', 'Foreign', 'Agent');
        $deletedAgent = $this->createAgent($ownerA->company_id, '0771001004', 'Deleted', 'Agent');
        $deletedAgent->delete();

        $this->actingAs($ownerA)
            ->get(route('ui.call-center.agents.index'))
            ->assertOk()
            ->assertSee('Own Agent')
            ->assertDontSee('Foreign Agent')
            ->assertDontSee('Deleted Agent')
            ->assertDontSee($foreignAgent->phone);
    }

    public function test_staff_without_permission_gets_403_on_list(): void
    {
        $owner = $this->createOwner();
        $staff = $this->createUser(
            (int) $owner->company_id,
            $this->staffRole,
            UserType::EMPLOYEE,
            'staff-'.Str::uuid().'@feeder.local',
            '0771001099',
        );

        $this->actingAs($staff)
            ->get(route('ui.call-center.agents.index'))
            ->assertForbidden();
    }

    public function test_call_center_agent_without_management_permission_gets_403_on_list(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0771001100', 'No', 'Access');

        $this->actingAs($agent)
            ->get(route('ui.call-center.agents.index'))
            ->assertForbidden();
    }

    public function test_owner_can_view_own_agent_profile(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0771001201', 'Kasuni', 'Fernando', '125.00');

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.show', $agent->uuid))
            ->assertOk()
            ->assertSee('Kasuni Fernando')
            ->assertSee('0771001201')
            ->assertSee('LKR 125.00')
            ->assertSee('cca.kasuni.fernando')
            ->assertSee('Order workflow statistics will appear here')
            ->assertDontSee('2554')
            ->assertDontSee('5517')
            ->assertDontSee('LKR 124,500.00');
    }

    public function test_owner_cannot_view_foreign_agent_profile(): void
    {
        $ownerA = $this->createOwner();
        $ownerB = $this->createOwner();
        $foreign = $this->createAgent($ownerB->company_id, '0771001202', 'Foreign', 'Profile');

        $this->actingAs($ownerA)
            ->get(route('ui.call-center.agents.show', $foreign->uuid))
            ->assertNotFound();
    }

    public function test_soft_deleted_agent_profile_is_not_found(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0771001203', 'Gone', 'Agent');
        $uuid = $agent->uuid;
        $agent->delete();

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.show', $uuid))
            ->assertNotFound();
    }

    public function test_employee_without_permission_gets_403_on_profile(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0771001204', 'Target', 'Agent');
        $staff = $this->createUser(
            (int) $owner->company_id,
            $this->staffRole,
            UserType::EMPLOYEE,
            'staff-profile-'.Str::uuid().'@feeder.local',
            '0771001205',
        );

        $this->actingAs($staff)
            ->get(route('ui.call-center.agents.show', $agent->uuid))
            ->assertForbidden();
    }

    public function test_search_is_company_scoped(): void
    {
        $ownerA = $this->createOwner();
        $ownerB = $this->createOwner();

        $this->createAgent($ownerA->company_id, '0771001301', 'Amali', 'Local');
        $this->createAgent($ownerB->company_id, '0771001302', 'Amali', 'Foreign');

        $this->actingAs($ownerA)
            ->get(route('ui.call-center.agents.index', ['search' => 'Amali']))
            ->assertOk()
            ->assertSee('Amali Local')
            ->assertDontSee('Amali Foreign');
    }

    public function test_active_and_inactive_status_filters(): void
    {
        $owner = $this->createOwner();
        $active = $this->createAgent($owner->company_id, '0771001401', 'Active', 'One');
        $inactive = $this->createAgent($owner->company_id, '0771001402', 'Inactive', 'Two');
        $inactive->forceFill(['status' => UserStatus::SUSPENDED->value])->save();

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('Active One')
            ->assertDontSee('Inactive Two');

        $this->actingAs($owner)
            ->get(route('ui.call-center.agents.index', ['status' => 'inactive']))
            ->assertOk()
            ->assertSee('Inactive Two')
            ->assertDontSee('Active One');
    }

    public function test_pagination_preserves_search_and_status_query_string(): void
    {
        $owner = $this->createOwner();

        for ($i = 1; $i <= 16; $i++) {
            $this->createAgent(
                $owner->company_id,
                '0772'.str_pad((string) $i, 6, '0', STR_PAD_LEFT),
                'Page',
                'Agent'.$i,
            );
        }

        $response = $this->actingAs($owner)
            ->get(route('ui.call-center.agents.index', [
                'search' => 'Page',
                'status' => 'active',
                'page' => 2,
            ]));

        $response->assertOk();
        $response->assertSee('Showing');
        $this->assertStringContainsString('search=Page', $response->getContent());
        $this->assertStringContainsString('status=active', $response->getContent());
    }

    public function test_owner_menu_includes_call_center_agents_and_agent_menu_does_not(): void
    {
        $owner = $this->createOwner();
        $agent = $this->createAgent($owner->company_id, '0771001501', 'Menu', 'Agent');
        $menuService = app(MenuService::class);

        $ownerMenu = $menuService->getForUser($owner->fresh(['role.portal', 'company.portal']));
        $ownerLabels = $this->flattenMenuLabels($ownerMenu);
        $this->assertContains('Agents', $ownerLabels);
        $this->assertContains('Call Center', $ownerLabels);

        $agentMenu = $menuService->getForUser($agent->fresh(['role.portal', 'company.portal']));
        $agentLabels = $this->flattenMenuLabels($agentMenu);
        $this->assertNotContains('Agents', $agentLabels);
        $this->assertNotContains('Call Center', $agentLabels);
    }

    public function test_service_pagination_applies_filters_without_loading_all_rows(): void
    {
        $owner = $this->createOwner();
        $this->createAgent($owner->company_id, '0771001601', 'Keep', 'Me', '10.00');
        $this->createAgent($owner->company_id, '0771001602', 'Skip', 'Me', '10.00');

        $page = app(AgentService::class)->paginateForCompany($owner, 'Keep', 'active', 10);

        $this->assertSame(1, $page->total());
        $this->assertSame('Keep', $page->first()->profile->first_name);
    }

    /**
     * @return list<string>
     */
    private function flattenMenuLabels(object $menu): array
    {
        $labels = [];

        foreach ($menu->getSections() as $section) {
            foreach ($section->getItems() as $item) {
                $labels[] = $item->getTitle();
                foreach ($item->getChildren() as $child) {
                    $labels[] = $child->getTitle();
                }
            }
        }

        return $labels;
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
