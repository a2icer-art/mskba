<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountContactsController;
use App\Http\Controllers\AccountModerationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\FilamentController;
use App\Http\Controllers\FilamentUsersModerationController;
use App\Http\Controllers\FilamentVenuesModerationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\VenuesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/register', [AuthController::class, 'register'])->name('register.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->prefix('account')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('account');
    Route::get('/profile', [AccountController::class, 'profile'])->name('account.profile');
    Route::get('/contacts', [AccountController::class, 'contacts'])->name('account.contacts');
    Route::get('/roles/{assignment}', [AccountController::class, 'role'])->name('account.roles.show');

    Route::post('/moderation-request', [AccountModerationController::class, 'store'])->name('account.moderation.store');

    Route::prefix('contacts')->name('account.contacts.')->group(function () {
        Route::post('/', [AccountContactsController::class, 'store'])->name('store');
        Route::patch('/{contact}', [AccountContactsController::class, 'update'])->name('update');
        Route::post('/{contact}/confirm-request', [AccountContactsController::class, 'requestConfirm'])
            ->name('confirm.request');
        Route::post('/{contact}/confirm-verify', [AccountContactsController::class, 'verifyConfirm'])
            ->name('confirm.verify');
        Route::delete('/{contact}', [AccountContactsController::class, 'destroy'])->name('destroy');
    });

    Route::patch('/emails/{contact}', [AccountContactsController::class, 'updateEmail'])->name('account.emails.update');
    Route::delete('/emails/{contact}', [AccountContactsController::class, 'destroyEmail'])->name('account.emails.destroy');
});

Route::prefix('venues')->group(function () {
    Route::get('/', [VenuesController::class, 'index'])->name('venues');
    Route::get('/{type}/{venue}', [VenuesController::class, 'show'])->name('venues.show');
    Route::post('/', [VenuesController::class, 'store'])->middleware('auth')->name('venues.store');
    Route::post('/{type}/{venue}/moderation-request', [VenuesController::class, 'submitModerationRequest'])
        ->middleware('auth')
        ->name('venues.moderation.request');
    Route::get('/{type}', [VenuesController::class, 'type'])->name('venues.type');
});

Route::middleware('auth')->prefix('filament')->group(function () {
    Route::get('/', [FilamentController::class, 'index'])->name('filament');
    Route::get('/users-moderation', [FilamentUsersModerationController::class, 'index'])
        ->name('filament.users.moderation');
    Route::post('/users-moderation/{moderationRequest}/approve', [FilamentUsersModerationController::class, 'approve'])
        ->name('filament.users.moderation.approve');
    Route::post('/users-moderation/{moderationRequest}/reject', [FilamentUsersModerationController::class, 'reject'])
        ->name('filament.users.moderation.reject');
    Route::post('/users-moderation/{moderationRequest}/block', [FilamentUsersModerationController::class, 'block'])
        ->name('filament.users.moderation.block');
    Route::post('/users-moderation/{moderationRequest}/unblock', [FilamentUsersModerationController::class, 'unblock'])
        ->name('filament.users.moderation.unblock');
    Route::get('/venues-moderation', [FilamentVenuesModerationController::class, 'index'])
        ->name('filament.venues.moderation');
    Route::post('/venues-moderation/{moderationRequest}/approve', [FilamentVenuesModerationController::class, 'approve'])
        ->name('filament.venues.moderation.approve');
    Route::post('/venues-moderation/{moderationRequest}/reject', [FilamentVenuesModerationController::class, 'reject'])
        ->name('filament.venues.moderation.reject');
    Route::post('/venues-moderation/{moderationRequest}/block', [FilamentVenuesModerationController::class, 'block'])
        ->name('filament.venues.moderation.block');
    Route::post('/venues-moderation/{moderationRequest}/unblock', [FilamentVenuesModerationController::class, 'unblock'])
        ->name('filament.venues.moderation.unblock');
});
