<?php

namespace App\Services\Auth;

use App\Http\Requests\Auth\LoginRequest;
use Feeder\Core\Enums\CompanyStatus;
use Feeder\Core\Enums\PortalCode;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;


class LoginService
{
    public function login(LoginRequest $request, PortalCode $portal): User
    {
        $user = $this->findUser($request->identifier);

        $this->validatePassword($request->password, $user);

        $this->validateUserStatus($user);

        $this->validateCompany($user, $portal);

        $this->authenticate($user, $request->remember);

        $this->updateLastLogin($user);

        return $user;
    }

    private function findUser(string $identifier): User
    {
        $user = User::query()
            ->with(['company.portal',])
            ->where(function ($query) use ($identifier) {
                $query->where('email', $identifier)
                    ->orWhere('phone', $identifier);
            })
            ->first();

        if (!$user) {
            throw ValidationException::withMessages([
                'identifier' => __('auth.failed'),
            ]);
        }

        return $user;
    }

    private function validatePassword(string $password, User $user): void
    {
        if (!Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'identifier' => __('auth.failed'),
            ]);
        }
    }

    private function validateUserStatus(User $user): void
    {
        if ($user->status !== UserStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'identifier' => $this->getUserStatusMessage($user->status),
            ]);
        }
    }

    private function validateCompany(User $user, PortalCode $portal): void
    {
        if (! $user->company) {
            throw ValidationException::withMessages([
                'identifier' => 'Your account is not linked to a company.',
            ]);
        }


        if ($user->company->status !== CompanyStatus::ACTIVE) {
            throw ValidationException::withMessages([
                'identifier' => $this->getCompanyStatusMessage(
                    $user->company->status
                ),
            ]);
        }

        if (! $user->company->portal) {
            throw ValidationException::withMessages([
                'identifier' => 'Your company portal is not configured.',
            ]);
        }

        if ($user->company->portal->code !== $portal->value) {
            throw ValidationException::withMessages([
                'identifier' => 'You cannot login from this portal.',
            ]);
        }

        if (! $user->company->portal->is_active) {
            throw ValidationException::withMessages([
                'identifier' => 'This portal is currently disabled.',
            ]);
        }
    }

    private function getCompanyStatusMessage(CompanyStatus $status): string
    {
        return match ($status) {
            CompanyStatus::PENDING =>
            'Your company is waiting for approval.',

            CompanyStatus::REJECTED =>
            'Your company application was rejected.',

            CompanyStatus::SUSPENDED =>
            'Your company has been suspended.',

            default =>
            'Your company cannot login.',
        };
    }

    private function authenticate(User $user, ?bool $remember): void
    {
        Auth::login($user, $remember);

        request()->session()->regenerate();
    }

    private function updateLastLogin(User $user): void
    {
        $user->update(['last_login_at' => now()]);
    }

    private function getUserStatusMessage(UserStatus $status): string
    {
        return match ($status) {
            UserStatus::REGISTERING =>
            'Your registration is not completed.',

            UserStatus::PENDING =>
            'Your account is waiting for approval.',

            UserStatus::REJECTED =>
            'Your account application was rejected.',

            UserStatus::SUSPENDED =>
            'Your account has been suspended.',

            UserStatus::DELETED =>
            'Your account is no longer available.',

            default =>
            'Your account cannot login.',
        };
    }
}
