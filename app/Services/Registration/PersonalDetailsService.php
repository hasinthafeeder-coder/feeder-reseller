<?php

namespace App\Services\Registration;

use Feeder\Core\Enums\ApplicationType;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Models\User;
use Feeder\Core\Models\UserProfile;
use Feeder\Core\Services\CountryRegistrationRuleService;
use Feeder\Core\Services\FileService;
use Feeder\Core\Services\UuidService;
use Feeder\Core\Support\IdentityDocumentStorage;
use Feeder\Core\Support\UserProfileSchema;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class PersonalDetailsService
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly CountryRegistrationRuleService $countryRegistrationRuleService,
    ) {}

    public function save(array $data): UserProfile
    {
        return DB::transaction(function () use ($data) {
            /** @var User|null $user */
            $user = User::query()
                ->with('profile')
                ->where('uuid', $data['user_uuid'])
                ->first();

            if (! $user) {
                throw ValidationException::withMessages([
                    'user_uuid' => 'Invalid Registration Session.',
                ]);
            }

            if ($user->status !== UserStatus::REGISTERING) {
                throw ValidationException::withMessages([
                    'user_uuid' => 'Registration cannot be updated.',
                ]);
            }

            $profile = $user->profile;

            if (! $profile) {
                $profile = new UserProfile();
                $profile->uuid = UuidService::generate();
                $profile->user_id = $user->id;
            }

            $countryRules = $this->countryRegistrationRuleService->resolveForResellerRegistration();
            $normalizedIdentityNumber = $countryRules->normalizeIdentityDocument($data['nic']);
            $this->assertIdentityDocumentIsUnique($normalizedIdentityNumber, $profile);

            $profilePhotoUuid = $this->resolveProfilePhotoUuid(
                $user,
                $profile,
                $data['profile_photo'] ?? null,
                $data['profile_photo_uuid'] ?? null,
            );

            $profile->first_name = $data['first_name'];
            $profile->last_name = $data['last_name'];
            IdentityDocumentStorage::applyToProfile($profile, $countryRules, $normalizedIdentityNumber);
            $profile->address = $data['address'];
            $profile->profile_photo = $profilePhotoUuid;
            $profile->save();

            return $profile->fresh();
        });
    }

    private function assertIdentityDocumentIsUnique(string $identityDocumentNumber, UserProfile $profile): void
    {
        $identityQuery = UserProfile::query();

        if (UserProfileSchema::hasIdentityDocumentColumns()) {
            $identityQuery->where(function ($query) use ($identityDocumentNumber): void {
                $query->where('identity_document_number', $identityDocumentNumber)
                    ->orWhere('nic', $identityDocumentNumber);
            });
        } else {
            $identityQuery->where('nic', $identityDocumentNumber);
        }

        if ($profile->exists) {
            $identityQuery->where('id', '!=', $profile->id);
        }

        if ($identityQuery->exists()) {
            throw ValidationException::withMessages([
                'nic' => 'This identity document number is already registered.',
            ]);
        }
    }

    private function resolveProfilePhotoUuid(
        User $user,
        UserProfile $profile,
        mixed $uploadedFile,
        ?string $existingUuid,
    ): string {
        if ($uploadedFile instanceof UploadedFile) {
            return $this->uploadProfilePhoto($uploadedFile, $user);
        }

        if (! empty($existingUuid)) {
            return $existingUuid;
        }

        if (! empty($profile->profile_photo)) {
            return $profile->profile_photo;
        }

        throw ValidationException::withMessages([
            'profile_photo' => 'Profile photo is required.',
        ]);
    }

    private function uploadProfilePhoto(UploadedFile $file, User $user): string
    {
        try {
            $response = $this->fileService->upload(
                $file,
                ApplicationType::RESELLER->value,
                'USER',
                $user->uuid,
                'PROFILE_PHOTO',
                $user->uuid,
            );
        } catch (RequestException|ConnectionException) {
            throw ValidationException::withMessages([
                'profile_photo' => 'Unable to upload profile photo. Please try again.',
            ]);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'profile_photo' => 'Unable to upload profile photo. Please try again.',
            ]);
        }

        $uuid = data_get($response, 'file.uuid');

        if (! is_string($uuid) || $uuid === '') {
            throw ValidationException::withMessages([
                'profile_photo' => 'Unable to upload profile photo. Please try again.',
            ]);
        }

        return $uuid;
    }
}
