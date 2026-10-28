<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
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

// Catalogue public (visiteurs inclus) : seuls les livres publiés sont renvoyés (voir BookController)
Route::get('/books', [BookController::class, 'index'])->middleware('throttle:120,1');
Route::get('/books/{book}', [BookController::class, 'show'])->middleware('throttle:120,1');

// Réglages publics (nom, durée de prêt...) : lecture seule pour tous
Route::get('/settings', [SettingsController::class, 'show'])->middleware('throttle:120,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', LogoutController::class);
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/email/verification-notification', [VerifyEmailController::class, 'resend'])
        ->middleware('throttle:6,1');

    // Écriture des livres réservée aux admins (BookPolicy) ; emprunts réservés aux comptes vérifiés
    Route::middleware('verified')->group(function () {
        Route::apiResource('books', BookController::class)->except(['index', 'show']);

        Route::get('/loans', [LoanController::class, 'index']);
        Route::post('/books/{book}/borrow', [LoanController::class, 'store']);
        Route::post('/loans/{loan}/return', [LoanController::class, 'return']);
        Route::post('/loans/{loan}/renew', [LoanController::class, 'renew']);

        // Administration
        Route::put('/settings', [SettingsController::class, 'update']);
        Route::get('/users', [UserController::class, 'index']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
    });
});
