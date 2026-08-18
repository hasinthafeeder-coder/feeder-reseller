<?php

namespace App\Http\Controllers\Registration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\SaveBankDetailsRequest;
use App\Http\Requests\Registration\SaveCompanyDetailsRequest;
use App\Http\Requests\Registration\SavePersonalDetailsRequest;
use App\Http\Requests\Registration\SubmitApplicationRequest;
use App\Http\Requests\Registration\SubmitRegistrationRequest;
use App\Http\Requests\Registration\VerifyOtpRequest;
use App\Http\Requests\Registration\VerifyPhoneRequest;
use App\Services\Registration\BankDetailsService;
use App\Services\Registration\CompanyDetailsService;
use App\Services\Registration\PersonalDetailsService;
use App\Services\Registration\RegistrationOtpService;
use App\Services\Registration\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Validation\ValidationException;

class RegistrationController extends Controller
{
    public function __construct(
        private readonly RegistrationOtpService $registrationOtpService,
        private readonly RegistrationService $registrationService,
        private readonly PersonalDetailsService $personalDetailsService,
        private readonly CompanyDetailsService $companyDetailsService,
        private readonly BankDetailsService $bankDetailsService,
    ) {}

    public function create(): View
    {
        return view('pages.auth.register', [
            'referralCode' => request()->query('ref'),
        ]);
    }

    public function sendOtp(VerifyPhoneRequest $request): JsonResponse
    {
        $result = $this->registrationOtpService->sendOtp($request->string('phone')->toString());

        return response()->json([
            'message' => 'OTP sent successfully.',
            'expires_in' => $result['expires_in'],
            'otp' => $result['otp'],
        ]);
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $phone = $request->string('phone')->toString();
        $otp = $request->string('otp')->toString();

        if (!$this->registrationOtpService->verifyOtp($phone, $otp)) {
            throw ValidationException::withMessages([
                'otp' => 'Invalid OTP.',
            ]);
        }

        $stepOneStatus = $this->registrationService->resolveStepOneStatus($phone);

        return response()->json([
            'message' => 'Phone number verified.',
            'phone_exists' => $stepOneStatus['phone_exists'],
            'can_proceed' => $stepOneStatus['can_proceed'],
            'password_is_set' => $stepOneStatus['password_is_set'],
            'user_uuid' => $stepOneStatus['user_uuid'],
            'current_step' => $stepOneStatus['current_step'],
            'registration_submitted' => $stepOneStatus['registration_submitted'],
            'alert_message' => $stepOneStatus['alert_message'],
        ]);
    }

    public function registerUser(
        SubmitRegistrationRequest $request
    ): JsonResponse {
        $phone = $request->string('phone')->toString();
        $referralCode = $request->string('referral_code')->toString() ?: $request->string('ref')->toString();

        if (! $this->registrationOtpService->isPhoneVerified($phone)) {
            throw ValidationException::withMessages([
                'phone' => 'Phone number is not verified.',
            ]);
        }

        $user = $this->registrationService->createOrResumeRegistration(
            phone: $phone,
            password: $request->string('password')->toString(),
            referralCode: $referralCode !== '' ? $referralCode : null,
        );

        $this->registrationOtpService->clear($phone);

        return response()->json([
            'message' => 'Registration step completed.',
            'user' => [
                'uuid' => $user->uuid,
                'phone' => $user->phone,
                'status' => $user->status,
            ],
        ]);
    }

    public function savePersonalDetails(SavePersonalDetailsRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('profile_photo')) {
            $data['profile_photo'] = $request->file('profile_photo');
        }

        $profile = $this->personalDetailsService->save($data);

        return response()->json([
            'message' => 'Personal details saved successfully.',
            'profile' => [
                'first_name' => $profile->first_name,
                'last_name' => $profile->last_name,
                'nic' => $profile->nic,
                'address' => $profile->address,
                'profile_photo' => $profile->profile_photo,
            ],
        ]);
    }

    public function saveCompanyDetails(SaveCompanyDetailsRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo');
        }

        if ($request->hasFile('business_reg_pdf')) {
            $data['business_reg_pdf'] = $request->file('business_reg_pdf');
        }

        $company = $this->companyDetailsService->save($data);

        return response()->json([
            'message' => 'Company details saved successfully.',
            'company' => [
                'name' => $company->name,
                'customer_care_phone' => $company->customer_care_phone,
                'registration_number' => $company->registration_number,
                'address' => $company->address?->address,
                'logo_uuid' => $company->logo_uuid,
                'business_reg_pdf_uuid' => $company->business_reg_pdf_uuid,
            ],
        ]);
    }

    public function saveBankDetails(SaveBankDetailsRequest $request): JsonResponse
    {
        $bankAccount = $this->bankDetailsService->save($request->validated());

        return response()->json([
            'message' => 'Bank details saved successfully.',
            'bank_account' => [
                'account_name' => $bankAccount->account_name,
                'bank_name' => $bankAccount->bank_name,
                'branch_name' => $bankAccount->branch_name,
                'account_number' => $bankAccount->account_number,
            ],
        ]);
    }

    public function getDraft(string $uuid): JsonResponse
    {
        $draft = $this->registrationService->getDraft($uuid);

        return response()->json([
            'draft' => $draft,
        ]);
    }

    public function submitApplication(SubmitApplicationRequest $request): JsonResponse
    {
        $referralCode = $request->string('referral_code')->toString() ?: $request->string('ref')->toString();

        $user = $this->registrationService->submitApplication(
            $request->string('user_uuid')->toString(),
            $referralCode !== '' ? $referralCode : null,
        );

        return response()->json([
            'message' => 'Registration application submitted successfully. Your account is pending administrator approval.',
            'user' => [
                'uuid' => $user->uuid,
                'status' => $user->status,
            ],
        ]);
    }
}
