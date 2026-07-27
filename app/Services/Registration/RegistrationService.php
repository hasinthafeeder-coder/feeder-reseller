<?php

namespace App\Services\Registration;

use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Enums\UserType;
use Feeder\Core\Models\User;
use Feeder\Core\Services\UuidService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class RegistrationService
{
    public function createRegisteringUser(string $phone, string $password): User
    {
        $existingByPhone = User::query()->where('phone', $phone)->first();

        if ($existingByPhone !== null) {
            throw ValidationException::withMessages([
                'phone' => 'This phone number is already registered.',
            ]);
        }

        $email = sprintf('%s@reseller.local', $phone);

        $existingByEmail = User::query()->where('email', $email)->first();

        if ($existingByEmail !== null) {
            throw ValidationException::withMessages([
                'phone' => 'This phone number cannot be used right now. Please try another number.',
            ]);
        }

        return DB::transaction(function () use ($phone, $password, $email): User {
            /** @var User $user */
            $user = User::query()->create([
                'uuid' => UuidService::generate(),
                'email' => $email,
                'phone' => $phone,
                'password' => Hash::make($password),
                'user_type' => UserType::OWNER->value,
                'status' => UserStatus::REGISTERING->value,
                'phone_verified_at' => now(),
            ]);

            return $user;
        });
    }
}