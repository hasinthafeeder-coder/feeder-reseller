<?php

namespace App\Http\Controllers\Registration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\SubmitRegistrationRequest;
use App\Http\Requests\Registration\VerifyOtpRequest;
use App\Http\Requests\Registration\VerifyPhoneRequest;
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
    ) {
    }

    public function create(): View
    {
        return view('pages.auth.register');
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
                'otp' => 'Invalid OTP code.',
            ]);
        }

        return response()->json([
            'message' => 'OTP verified successfully.',
        ]);
    }

    public function registerUser(SubmitRegistrationRequest $request): JsonResponse
    {
        $phone = $request->string('phone')->toString();

        if (!$this->registrationOtpService->isPhoneVerified($phone)) {
            throw ValidationException::withMessages([
                'phone' => 'Phone number is not verified.',
            ]);
        }

        $user = $this->registrationService->createRegisteringUser(
            $phone,
            $request->string('password')->toString(),
        );

        $this->registrationOtpService->clear($phone);

        return response()->json([
            'message' => 'User registration step completed.',
            'user' => [
                'uuid' => $user->uuid,
                'phone' => $user->phone,
                'status' => $user->status,
            ],
        ], 201);
    }
}