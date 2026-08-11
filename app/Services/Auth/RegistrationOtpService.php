<?php

namespace App\Services\Auth;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class RegistrationOtpService
{
    private const TTL_SECONDS = 300;

    public function sendOtp(string $phone): array
    {
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

    public function isPhoneVerified(string $phone): bool
    {
        $cached = Cache::get($this->cacheKey($phone));

        return is_array($cached) && ($cached['verified'] ?? false) === true;
    }

    public function clear(string $phone): void
    {
        Cache::forget($this->cacheKey($phone));
    }

    private function cacheKey(string $phone): string
    {
        return "reseller_registration_otp:{$phone}";
    }
}
