<?php

namespace Tests\Feature\Referral;

use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\User;
use Feeder\Core\Services\MasterResellerService;
use Feeder\Core\Services\Referral\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReferralFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_reseller_is_created_with_a_referral_code(): void
    {
        $master = $this->createReseller('master@feeder.local', '0700000001');

        app(MasterResellerService::class)->setMaster($master);

        $referralCode = app(ReferralService::class)->ensureUserHasReferralCode($master);

        $this->assertTrue((bool) $master->fresh()->is_master_reseller);
        $this->assertNotEmpty($referralCode->code);
        $this->assertTrue($referralCode->is_active);
        $this->assertSame($master->id, $referralCode->user_id);
    }

    public function test_referral_code_validation_rejects_self_referral(): void
    {
        $master = $this->createReseller('master@feeder.local', '0700000001');
        app(MasterResellerService::class)->setMaster($master);
        $masterReferral = app(ReferralService::class)->ensureUserHasReferralCode($master);

        $this->expectException(ValidationException::class);
        app(ReferralService::class)->createPermanentRelationship($master, $masterReferral->code);
    }

    public function test_referral_code_validation_rejects_inactive_code(): void
    {
        $master = $this->createReseller('master@feeder.local', '0700000001');
        app(MasterResellerService::class)->setMaster($master);
        $masterReferral = app(ReferralService::class)->ensureUserHasReferralCode($master);

        $child = $this->createReseller('child@feeder.local', '0700000002');
        $masterReferral->update(['is_active' => false]);

        $this->expectException(ValidationException::class);
        app(ReferralService::class)->validateReferralCode($masterReferral->code, $child);
    }

    public function test_referral_relationship_is_stored_permanently(): void
    {
        $master = $this->createReseller('master@feeder.local', '0700000001');
        app(MasterResellerService::class)->setMaster($master);
        $masterCode = app(ReferralService::class)->ensureUserHasReferralCode($master);

        $parent = $this->createReseller('parent@feeder.local', '0700000002');
        $parentCode = app(ReferralService::class)->ensureUserHasReferralCode($parent);

        $child = $this->createReseller('child@feeder.local', '0700000003');
        $relationship = app(ReferralService::class)->createPermanentRelationship($child, $parentCode->code);

        $this->assertEquals($parent->id, $relationship->parent_user_id);
        $this->assertEquals($child->id, $relationship->child_user_id);
        $this->assertNotNull($child->fresh()->parentReseller()->first());

        $this->expectException(ValidationException::class);
        app(ReferralService::class)->createPermanentRelationship($child, $masterCode->code);
    }

    protected function createReseller(string $email, string $phone): User
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

        $company = Company::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'uuid' => (string) Str::uuid(),
                'portal_id' => $portal->id,
                'name' => $email,
                'email' => $email,
                'phone' => $phone,
                'registration_number' => 'REG-' . $phone,
                'status' => CompanyStatus::ACTIVE->value,
            ]
        );

        $user = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'email' => $email,
            'phone' => $phone,
            'password' => Hash::make('password'),
            'user_type' => UserType::OWNER->value,
            'status' => UserStatus::ACTIVE->value,
            'phone_verified_at' => now(),
            'is_master_reseller' => false,
        ]);

        $company->forceFill(['owner_user_id' => $user->id])->save();

        return $user;
    }
}
