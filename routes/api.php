<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Middleware\AuthenticateWithToken;
use App\Http\Middleware\EnsureCompanyIsActive;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ─── Onboarding (public) ─────────────────────────────────────────────────
    Route::prefix('onboarding')->group(function () {
        Route::post('register', [OnboardingController::class, 'register']);
    });

    // ─── Auth (public) ────────────────────────────────────────────────────────
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
        Route::post('verify-email', [AuthController::class, 'verifyEmail']);
    });

    // ─── Auth (protected) ─────────────────────────────────────────────────────
    Route::prefix('auth')
        ->middleware([AuthenticateWithToken::class, EnsureCompanyIsActive::class])
        ->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('logout-all', [AuthController::class, 'logoutAll']);
            Route::post('send-verification', [AuthController::class, 'sendVerification']);
        });
});
