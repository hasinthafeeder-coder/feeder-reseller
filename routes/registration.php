<?php

use App\Http\Controllers\Registration\RegistrationController;
use App\Http\Controllers\Registration\RegistrationUploadController;
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

    Route::post('/auth/register/personal', [RegistrationController::class, 'savePersonalDetails'])
        ->name('reseller.registration.personal');

    Route::post('/auth/register/company', [RegistrationController::class, 'saveCompanyDetails'])
        ->name('reseller.registration.company');

    Route::post('/auth/register/upload', [RegistrationUploadController::class, 'store'])
        ->name('reseller.registration.upload');

    Route::post('/auth/register/bank', [RegistrationController::class, 'saveBankDetails'])
        ->name('reseller.registration.bank');

    Route::get('/auth/register/draft/{uuid}', [RegistrationController::class, 'getDraft'])
        ->name('reseller.registration.draft');

    Route::post('/auth/register/submit', [RegistrationController::class, 'submitApplication'])
        ->name('reseller.registration.submit');
});
