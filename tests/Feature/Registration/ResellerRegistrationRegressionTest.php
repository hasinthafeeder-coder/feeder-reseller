<?php

namespace Tests\Feature\Registration;

use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\User;
use Feeder\Core\Services\UuidService;
use Illuminate\Support\Facades\Hash;
use Tests\Support\UsesMysqlTestDatabase;
use Tests\TestCase;

class ResellerRegistrationRegressionTest extends TestCase
{
    use UsesMysqlTestDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpMysqlTestDatabase();
        $this->seedResellerPortal();
    }

    protected function tearDown(): void
    {
        $this->tearDownMysqlTestDatabase();

        parent::tearDown();
    }

    public function test_existing_reseller_registration_still_works(): void
    {
        $user = User::query()->create([
            'uuid' => UuidService::generate(),
            'email' => 'reseller@example.com',
            'phone' => '0711111111',
            'password' => Hash::make('Password123!'),
            'user_type' => 'OWNER',
            'status' => UserStatus::REGISTERING->value,
            'phone_verified_at' => now(),
        ]);

        $response = $this->withHeader('Accept', 'application/json')->post('/auth/register/personal', [
            'user_uuid' => $user->uuid,
            'first_name' => 'Kamal',
            'last_name' => 'Silva',
            'nic' => '123456789V',
            'address' => 'Colombo',
            'profile_photo_uuid' => 'PHOTO12345',
        ]);

        $response->assertOk();
        $response->assertJsonPath('profile.nic', '123456789V');
    }

    public function test_reseller_registration_does_not_assign_home_country(): void
    {
        $user = User::query()->create([
            'uuid' => UuidService::generate(),
            'email' => 'reseller-home@example.com',
            'phone' => '0722222222',
            'password' => Hash::make('Password123!'),
            'user_type' => 'OWNER',
            'status' => UserStatus::REGISTERING->value,
            'phone_verified_at' => now(),
        ]);

        $user->load('company');

        $this->assertNull($user->company?->home_country_id);
    }

    public function test_reseller_registration_rejects_invalid_sri_lankan_nic(): void
    {
        $user = User::query()->create([
            'uuid' => UuidService::generate(),
            'email' => 'reseller2@example.com',
            'phone' => '0733333333',
            'password' => Hash::make('Password123!'),
            'user_type' => 'OWNER',
            'status' => UserStatus::REGISTERING->value,
            'phone_verified_at' => now(),
        ]);

        $response = $this->withHeader('Accept', 'application/json')->post('/auth/register/personal', [
            'user_uuid' => $user->uuid,
            'first_name' => 'Kamal',
            'last_name' => 'Silva',
            'nic' => 'INVALID',
            'address' => 'Colombo',
            'profile_photo_uuid' => 'PHOTO12345',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['nic']);
    }

    public function test_reseller_phone_validation_still_accepts_sri_lankan_numbers(): void
    {
        $response = $this->withHeader('Accept', 'application/json')->post('/auth/register/send-otp', [
            'phone' => '0744444444',
        ]);

        $response->assertOk();
    }

    private function seedResellerPortal(): void
    {
        Portal::query()->firstOrCreate(
            ['code' => PortalCode::RESELLER->value],
            [
                'uuid' => UuidService::generate(),
                'name' => 'Reseller Portal',
                'is_active' => true,
            ]
        );
    }
}
