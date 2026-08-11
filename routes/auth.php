<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordResetController;

Route::middleware('guest')->group(function () {
    // Login Routes
    Route::get('/login', [LoginController::class, 'create'])
        ->name('login');

    Route::post('/login', [LoginController::class, 'store'])
        ->name('login.store');

    // Password Reset Routes
    // Step 1: Enter phone number
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])
        ->name('password.request');

    // Step 2: Send OTP
    Route::post('/forgot-password', [PasswordResetController::class, 'sendOtp'])
        ->name('password.send');

    // Step 3: Show OTP verification page
    Route::get('/verify-reset-otp', [PasswordResetController::class, 'verify'])
        ->name('password.reset.verify');

    // Step 4: Verify OTP
    Route::post('/verify-reset-otp', [PasswordResetController::class, 'verifyOtp'])
        ->name('password.reset.verify.submit');

    // Step 5: Show new password page
    Route::get('/reset-password', [PasswordResetController::class, 'resetForm'])
        ->name('password.reset.form');

    // Step 6: Update password
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])
        ->name('password.reset.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [
        LogoutController::class,
        'destroy'
    ])
        ->name('logout');
});
