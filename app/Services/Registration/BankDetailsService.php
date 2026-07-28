<?php

namespace App\Services\Registration;

use Feeder\Core\Enums\UserStatus;
use Feeder\Core\Models\CompanyBankAccount;
use Feeder\Core\Models\User;
use Feeder\Core\Services\UuidService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankDetailsService
{
    public function save(array $data): CompanyBankAccount
    {
        return DB::transaction(function () use ($data) {
            /** @var User|null $user */
            $user = User::query()
                ->with('company.bankAccount')
                ->where('uuid', $data['user_uuid'])
                ->first();

            if (! $user) {
                throw ValidationException::withMessages([
                    'user_uuid' => 'Invalid Registration Session.',
                ]);
            }

            if ($user->status !== UserStatus::REGISTERING->value) {
                throw ValidationException::withMessages([
                    'user_uuid' => 'Registration cannot be updated.',
                ]);
            }

            $company = $user->company;

            if (! $company) {
                throw ValidationException::withMessages([
                    'user_uuid' => 'Company details must be completed before adding bank details.',
                ]);
            }

            $bankAccount = $company->bankAccount;

            if (! $bankAccount) {
                $bankAccount = new CompanyBankAccount();
                $bankAccount->uuid = UuidService::generate();
                $bankAccount->company_id = $company->id;
            }

            $bankAccount->account_name = $data['account_name'];
            $bankAccount->bank_name = $data['bank_name'];
            $bankAccount->branch_name = $data['branch_name'];
            $bankAccount->bank_code = $data['bank_code'] ?? null;
            $bankAccount->branch_code = $data['branch_code'] ?? null;
            $bankAccount->account_number = $data['account_number'];
            $bankAccount->save();

            return $bankAccount;
        });
    }
}
