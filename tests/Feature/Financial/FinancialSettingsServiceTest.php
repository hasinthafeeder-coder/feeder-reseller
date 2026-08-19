<?php

namespace Tests\Feature\Financial;

use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\ReferralRelationship;
use Feeder\Core\Models\User;
use Feeder\Core\Services\IntroducerBonusService;
use Feeder\Core\Services\Referral\ReferralService;
use Feeder\Core\Services\ResellerServiceChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinancialSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_service_charge_applies_without_override(): void
    {
        $service = app(ResellerServiceChargeService::class);
        $service->setDefaultCharge(75);

        $reseller = $this->createReseller('a@feeder.local', '0700000001');

        $this->assertSame('75.00', $service->getEffectiveCharge($reseller));
    }

    public function test_reseller_override_takes_priority_over_default_charge(): void
    {
        $service = app(ResellerServiceChargeService::class);
        $service->setDefaultCharge(75);

        $reseller = $this->createReseller('b@feeder.local', '0700000002');
        $service->setResellerOverride($reseller, 100);

        $this->assertSame('100.00', $service->getEffectiveCharge($reseller));
    }

    public function test_default_change_keeps_override_independent(): void
    {
        $service = app(ResellerServiceChargeService::class);
        $service->setDefaultCharge(80);

        $resellerA = $this->createReseller('a@feeder.local', '0700000003');
        $resellerB = $this->createReseller('b@feeder.local', '0700000004');

        $service->setResellerOverride($resellerB, 100);

        $this->assertSame('80.00', $service->getEffectiveCharge($resellerA));
        $this->assertSame('100.00', $service->getEffectiveCharge($resellerB));
    }

    public function test_clear_override_returns_to_default_charge(): void
    {
        $service = app(ResellerServiceChargeService::class);
        $service->setDefaultCharge(80);

        $reseller = $this->createReseller('c@feeder.local', '0700000005');
        $service->setResellerOverride($reseller, 100);
        $service->clearResellerOverride($reseller);

        $this->assertSame('80.00', $service->getEffectiveCharge($reseller));
    }

    public function test_direct_introducer_resolves_to_nearest_parent(): void
    {
        $master = $this->createReseller('master@feeder.local', '0700000006');
        $a = $this->createReseller('a@feeder.local', '0700000007');
        $b = $this->createReseller('b@feeder.local', '0700000008');
        $c = $this->createReseller('c@feeder.local', '0700000009');

        $this->createRelationship($master, $a);
        $this->createRelationship($a, $b);
        $this->createRelationship($b, $c);

        $introducer = app(ReferralService::class)->getDirectIntroducer($c);

        $this->assertNotNull($introducer);
        $this->assertSame($b->id, $introducer->id);
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

    protected function createRelationship(User $parent, User $child): ReferralRelationship
    {
        return ReferralRelationship::query()->create([
            'uuid' => (string) Str::uuid(),
            'parent_user_id' => $parent->id,
            'child_user_id' => $child->id,
            'source_referral_code_id' => null,
        ]);
    }
}
