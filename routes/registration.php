<?php

use App\Http\Controllers\Registration\RegistrationController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/auth/register', [RegistrationController::class, 'create'])
        ->name('reseller.registration.create');

    Route::post('/auth/register/send-otp', [RegistrationController::class, 'sendOtp'])
        ->name('reseller.registration.send-otp');

    Route::post('/auth/register/verify-otp', [RegistrationController::class, 'verifyOtp'])
        ->name('reseller.registration.verify-otp');

    Route::post('/auth/register/user', [RegistrationController::class, 'registerUser'])
        ->name('reseller.registration.user');
});