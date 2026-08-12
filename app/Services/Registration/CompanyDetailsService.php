<?php

namespace App\Services\Registration;

use Feeder\Core\Enums\ApplicationType;
use Feeder\Core\Enums\FileCategory;
use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Models\Company;
use Feeder\Core\Models\CompanyAddress;
use Feeder\Core\Models\Portal;
use Feeder\Core\Models\User;
use Feeder\Core\Services\FileService;
use Feeder\Core\Services\UuidService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class CompanyDetailsService
{
    public function __construct(
        private readonly FileService $fileService,
    ) {}

    public function save(array $data): Company
    {
        return DB::transaction(function () use ($data) {
            /** @var User|null $user */
            $user = User::query()
                ->with(['company.address'])
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

            $portal = Portal::query()->where('code', 'RESELLER')->first();
            $portalId = $portal ? $portal->id : 1;

            $company = $user->company;

            if (! $company) {
                $company = new Company();
                $company->uuid = UuidService::generate();
                $company->portal_id = $portalId;
                $company->owner_user_id = $user->id;
                $company->status = 'PENDING';
            }

            $company->name = $data['name'];
            $company->phone = $user->phone ?? '';
            $company->customer_care_phone = $data['customer_care_phone'];
            $company->registration_number = $data['registration_number'] ?? null;
            $company->save();

            if ($user->company_id !== $company->id) {
                $user->company_id = $company->id;
                $user->save();
            }

            $companyAddress = $company->address;

            if (! $companyAddress) {
                $companyAddress = new CompanyAddress();
                $companyAddress->uuid = UuidService::generate();
                $companyAddress->company_id = $company->id;
            }

            $companyAddress->address = $data['address'];
            $companyAddress->save();

            $company->logo_uuid = $this->resolveLogoUuid(
                $user,
                $company,
                $data['logo'] ?? null,
                $data['logo_uuid'] ?? null,
            );

            $company->business_reg_pdf_uuid = $this->resolveBusinessRegPdfUuid(
                $user,
                $company,
                $data['business_reg_pdf'] ?? null,
                $data['business_reg_pdf_uuid'] ?? null,
            );

            $company->save();

            return $company->fresh(['address']);
        });
    }

    public function uploadRegistrationFile(
        string $userUuid,
        string $category,
        UploadedFile $file,
        ?string $entityType = null,
        ?string $entityUuid = null,
    ): array {
        /** @var User|null $user */
        $user = User::query()
            ->with('company')
            ->where('uuid', $userUuid)
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

        $resolvedEntityType = $entityType;
        $resolvedEntityUuid = $entityUuid;

        if ($resolvedEntityType === null || $resolvedEntityUuid === null) {
            [$resolvedEntityType, $resolvedEntityUuid] = $this->defaultEntityForCategory($user, $category);
        }

        try {
            $response = $this->fileService->upload(
                $file,
                ApplicationType::RESELLER->value,
                $resolvedEntityType,
                $resolvedEntityUuid,
                $category,
                $user->uuid,
            );
        } catch (RequestException|ConnectionException|Throwable) {
            throw ValidationException::withMessages([
                'file' => 'Unable to upload file. Please try again.',
            ]);
        }

        $uuid = data_get($response, 'file.uuid');

        if (! is_string($uuid) || $uuid === '') {
            throw ValidationException::withMessages([
                'file' => 'Unable to upload file. Please try again.',
            ]);
        }

        return [
            'uuid' => $uuid,
            'category' => data_get($response, 'file.category', $category),
            'original_name' => data_get($response, 'file.original_name'),
            'mime_type' => data_get($response, 'file.mime_type'),
            'size' => data_get($response, 'file.size'),
            'entity_type' => $resolvedEntityType,
            'entity_uuid' => $resolvedEntityUuid,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function defaultEntityForCategory(User $user, string $category): array
    {
        if (
            in_array($category, [
                FileCategory::COMPANY_LOGO->value,
                FileCategory::BUSINESS_REGISTRATION->value,
            ], true)
            && $user->company
        ) {
            return ['COMPANY', $user->company->uuid];
        }

        return ['USER', $user->uuid];
    }

    private function resolveLogoUuid(
        User $user,
        Company $company,
        mixed $uploadedFile,
        ?string $existingUuid,
    ): string {
        if ($uploadedFile instanceof UploadedFile) {
            return $this->uploadCompanyFile(
                $uploadedFile,
                $user,
                $company,
                FileCategory::COMPANY_LOGO->value,
                'logo',
            );
        }

        if (! empty($existingUuid)) {
            return $existingUuid;
        }

        if (! empty($company->logo_uuid)) {
            return $company->logo_uuid;
        }

        throw ValidationException::withMessages([
            'logo' => 'Company logo is required.',
        ]);
    }

    private function resolveBusinessRegPdfUuid(
        User $user,
        Company $company,
        mixed $uploadedFile,
        ?string $existingUuid,
    ): ?string {
        if ($uploadedFile instanceof UploadedFile) {
            return $this->uploadCompanyFile(
                $uploadedFile,
                $user,
                $company,
                FileCategory::BUSINESS_REGISTRATION->value,
                'business_reg_pdf',
            );
        }

        if (! empty($existingUuid)) {
            return $existingUuid;
        }

        return $company->business_reg_pdf_uuid ?: null;
    }

    private function uploadCompanyFile(
        UploadedFile $file,
        User $user,
        Company $company,
        string $category,
        string $errorField,
    ): string {
        try {
            $response = $this->fileService->upload(
                $file,
                ApplicationType::RESELLER->value,
                'COMPANY',
                $company->uuid,
                $category,
                $user->uuid,
            );
        } catch (RequestException|ConnectionException|Throwable) {
            throw ValidationException::withMessages([
                $errorField => 'Unable to upload file. Please try again.',
            ]);
        }

        $uuid = data_get($response, 'file.uuid');

        if (! is_string($uuid) || $uuid === '') {
            throw ValidationException::withMessages([
                $errorField => 'Unable to upload file. Please try again.',
            ]);
        }

        return $uuid;
    }
}
