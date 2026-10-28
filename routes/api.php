<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\BookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Authentification (session par cookie, voir Sanctum SPA)
Route::post('/register', RegisterController::class)->middleware('throttle:6,1');
Route::post('/login', LoginController::class);
Route::post('/forgot-password', ForgotPasswordController::class)->middleware('throttle:6,1');
Route::post('/reset-password', ResetPasswordController::class)->middleware('throttle:6,1');

Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verify'])
    ->middleware(['signed', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', LogoutController::class);
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/email/verification-notification', [VerifyEmailController::class, 'resend'])
        ->middleware('throttle:6,1');

    // Livres : lecture pour tout utilisateur connecté et vérifié, écriture réservée aux admins (BookPolicy)
    Route::middleware('verified')->group(function () {
        Route::apiResource('books', BookController::class);
    });
});
