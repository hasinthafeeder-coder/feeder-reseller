<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgetPasswordRequest;
use App\Http\Requests\Auth\VerifyResetOtpRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    public function __construct(
        private readonly PasswordResetService $passwordResetService
    ) {}

    public function create(): View
    {
        return view('pages.auth.forgot-password');
    }

    public function sendOtp(
        ForgetPasswordRequest $request
    ): RedirectResponse {

        $result = $this->passwordResetService->sendOtp(
            $request->phone
        );

        return redirect()
            ->route('password.reset.verify', [
                'phone' => $request->phone,
            ])
            ->with('debug_otp', $result['otp']);
    }

    public function verify(): View
    {
        return view('pages.auth.verify-reset-otp');
    }

    public function verifyOtp(
        VerifyResetOtpRequest $request
    ): RedirectResponse {

        $verified = $this->passwordResetService
            ->verifyOtp(
                $request->phone,
                $request->otp
            );

        if (! $verified) {
            return back()
                ->withErrors([
                    'otp' => 'Invalid OTP.',
                ]);
        }

        return redirect()
            ->route('password.reset.form', [
                'phone' => $request->phone,
            ]);
    }

    public function resetForm(): View
    {
        return view('pages.auth.reset-password');
    }

    public function resetPassword(
        ResetPasswordRequest $request
    ): RedirectResponse {

        $this->passwordResetService
            ->resetPassword(
                $request->phone,
                $request->password
            );

        return redirect()
            ->route('login')
            ->with(
                'success',
                'Password updated successfully. Please login.'
            );
    }
}
