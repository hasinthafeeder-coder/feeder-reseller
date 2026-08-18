<?php

namespace Tests\Feature\Team;

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
use Feeder\Core\Services\Referral\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeamTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseller_root_and_depth_boundaries_are_enforced(): void
    {
        [$master, $resellerA, $a1, $a1_1, $a1_1_1, $a1_1_1_1, $branchB] = $this->createHierarchy();

        $this->actingAs($resellerA);

        $rootResponse = $this->getJson(route('team.structure.root'));
        $rootResponse->assertOk()
            ->assertJsonPath('root.user_id', $resellerA->id);

        $level1Response = $this->getJson(route('team.structure.children', $resellerA->uuid));
        $level1Response->assertOk();
        $this->assertSame([$a1->id], collect($level1Response->json('children'))->pluck('user_id')->all());

        $level2Response = $this->getJson(route('team.structure.children', $a1->uuid));
        $level2Response->assertOk();
        $this->assertSame([$a1_1->id], collect($level2Response->json('children'))->pluck('user_id')->all());

        $level3Response = $this->getJson(route('team.structure.children', $a1_1->uuid));
        $level3Response->assertOk();
        $this->assertSame([$a1_1_1->id], collect($level3Response->json('children'))->pluck('user_id')->all());

        $level4Response = $this->getJson(route('team.structure.children', $a1_1_1->uuid));
        $level4Response->assertOk()->assertJsonPath('children', []);

        $this->getJson(route('team.structure.children', $master->uuid))->assertNotFound();
        $this->getJson(route('team.structure.children', $branchB->uuid))->assertNotFound();
        $this->getJson(route('team.structure.path', $a1_1_1_1->uuid))->assertNotFound();
    }

    public function test_search_is_limited_to_reseller_scope_and_depth(): void
    {
        [$master, $resellerA, $a1, $a1_1, $a1_1_1, $a1_1_1_1, $branchB, $branchB1] = $this->createHierarchy();

        $this->actingAs($resellerA);

        $this->assertSearchContains('A1 Child', [$a1->id]);
        $this->assertSearchContains('A1-1 Child', [$a1_1->id]);
        $this->assertSearchContains('A1-1-1 Child', [$a1_1_1->id]);

        $this->assertSearchContains('Master Company', []);
        $this->assertSearchContains('Branch B Company', []);
        $this->assertSearchContains('B1 Child', []);
        $this->assertSearchContains('A1-1-1-1 Child', []);
        $this->assertSearchContains('Unknown User', []);
    }

    private function assertSearchContains(string $query, array $expectedUserIds): void
    {
        $response = $this->getJson(route('team.structure.search', ['q' => $query]));
        $response->assertOk();

        $actualIds = collect($response->json('results'))->pluck('user_id')->all();
        $this->assertSame($expectedUserIds, $actualIds);
    }

    private function createHierarchy(): array
    {
        [$portal, $role] = $this->createResellerAccessSetup();

        $master = $this->createReseller($portal, $role, 'master@tree.local', '0701000001', 'Master Company', 'Master');
        $resellerA = $this->createReseller($portal, $role, 'a@tree.local', '0701000002', 'Reseller A Company', 'A Root');
        $a1 = $this->createReseller($portal, $role, 'a1@tree.local', '0701000003', 'A1 Child Company', 'A1 Child');
        $a1_1 = $this->createReseller($portal, $role, 'a1-1@tree.local', '0701000004', 'A1-1 Child Company', 'A1-1 Child');
        $a1_1_1 = $this->createReseller($portal, $role, 'a1-1-1@tree.local', '0701000005', 'A1-1-1 Child Company', 'A1-1-1 Child');
        $a1_1_1_1 = $this->createReseller($portal, $role, 'a1-1-1-1@tree.local', '0701000006', 'A1-1-1-1 Child Company', 'A1-1-1-1 Child');
        $branchB = $this->createReseller($portal, $role, 'b@tree.local', '0701000007', 'Branch B Company', 'Branch B');
        $branchB1 = $this->createReseller($portal, $role, 'b1@tree.local', '0701000008', 'Branch B1 Company', 'B1 Child');

        $referralService = app(ReferralService::class);

        $masterCode = $referralService->ensureUserHasReferralCode($master)->code;
        $aCode = $referralService->ensureUserHasReferralCode($resellerA)->code;
        $a1Code = $referralService->ensureUserHasReferralCode($a1)->code;
        $a1_1Code = $referralService->ensureUserHasReferralCode($a1_1)->code;
        $a1_1_1Code = $referralService->ensureUserHasReferralCode($a1_1_1)->code;
        $bCode = $referralService->ensureUserHasReferralCode($branchB)->code;

        $master->update(['is_master_reseller' => true]);
        $referralService->createPermanentRelationship($resellerA, $masterCode);
        $referralService->createPermanentRelationship($branchB, $masterCode);
        $referralService->createPermanentRelationship($a1, $aCode);
        $referralService->createPermanentRelationship($a1_1, $a1Code);
        $referralService->createPermanentRelationship($a1_1_1, $a1_1Code);
        $referralService->createPermanentRelationship($a1_1_1_1, $a1_1_1Code);
        $referralService->createPermanentRelationship($branchB1, $bCode);

        return [$master, $resellerA, $a1, $a1_1, $a1_1_1, $a1_1_1_1, $branchB, $branchB1];
    }

    private function createResellerAccessSetup(): array
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

        $permission = Permission::query()->firstOrCreate(
            [
                'portal_id' => $portal->id,
                'slug' => 'team.structure.view',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'module' => 'Team',
                'group' => 'Team Structure',
                'name' => 'View Team Structure',
                'description' => null,
                'sort_order' => 10,
            ]
        );

        $role = Role::query()->firstOrCreate(
            [
                'portal_id' => $portal->id,
                'slug' => 'owner',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'company_id' => null,
                'name' => 'Owner',
                'description' => 'Owner',
                'is_system' => true,
            ]
        );

        $role->permissions()->syncWithoutDetaching([$permission->id]);

        return [$portal, $role];
    }

    private function createReseller(
        Portal $portal,
        Role $role,
        string $email,
        string $phone,
        string $companyName,
        string $firstName
    ): User {
        $company = Company::query()->create([
            'uuid' => (string) Str::uuid(),
            'portal_id' => $portal->id,
            'name' => $companyName,
            'email' => $email,
            'phone' => $phone,
            'registration_number' => 'REG-' . $phone,
            'status' => CompanyStatus::ACTIVE->value,
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'role_id' => $role->id,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make('password'),
            'user_type' => UserType::OWNER->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
            'is_master_reseller' => false,
        ]);

        UserProfile::query()->create([
            'user_id' => $user->id,
            'first_name' => $firstName,
            'last_name' => 'Test',
        ]);

        $company->forceFill(['owner_user_id' => $user->id])->save();

        return $user;
    }
}
