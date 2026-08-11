<?php

namespace App\Services\Auth;

use Feeder\Core\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordResetService
{
    private const TTL_SECONDS = 300;

    public function sendOtp(string $phone): array
    {
        $user = $this->findUser($phone);

        $otp = (string) random_int(100000, 999999);

        Cache::put(
            $this->cacheKey($phone),
            [
                'hash' => Hash::make($otp),
                'verified' => false,
                'expires_at' => now()->addSeconds(self::TTL_SECONDS)->toIso8601String(),
            ],
            now()->addSeconds(self::TTL_SECONDS),
        );

        return [
            'expires_in' => self::TTL_SECONDS,
            'otp' => config('app.debug') ? $otp : null,
        ];
    }

    public function verifyOtp(string $phone, string $otp): bool
    {
        $cached = Cache::get($this->cacheKey($phone));

        if (!is_array($cached) || !isset($cached['hash'])) {
            return false;
        }

        if (!Hash::check($otp, (string) $cached['hash'])) {
            return false;
        }

        $cached['verified'] = true;

        Cache::put(
            $this->cacheKey($phone),
            $cached,
            now()->addSeconds(self::TTL_SECONDS),
        );

        return true;
    }

    public function resetPassword(string $phone, string $password): void
    {
        $this->endureOtpVerified($phone);

        $user = $this->findUser($phone);

        $user->update([
            'password' => Hash::make($password),
        ]);

        $this->clear($phone);
    }

    public function endureOtpVerified(string $phone): void
    {
        $cached = Cache::get($this->cacheKey($phone));

        if (!is_array($cached) || ($cached['verified'] ?? false) !== true) {
            throw ValidationException::withMessages([
                'otp' => 'OTP is not verified or has expired. Please request a new OTP.',
            ]);
        }
    }

    private function findUser(string $phone): User
    {
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'phone' => 'No user found with the provided phone number. Please Register first.',
            ]);
        }

        return $user;
    }

    public function clear(string $phone): void
    {
        Cache::forget($this->cacheKey($phone));
    }

    private function cacheKey(string $phone): string
    {
        return "password_reset_otp_{$phone}";
    }
}
