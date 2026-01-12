<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountProfileController;
use App\Http\Controllers\AccountContactsController;
use App\Http\Controllers\AccountModerationController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminLogsController;
use App\Http\Controllers\AdminUsersModerationController;
use App\Http\Controllers\AdminVenuesModerationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Integrations\AddressSuggestController;
use App\Http\Controllers\VenuesController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/register', [AuthController::class, 'register'])->name('register.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->prefix('account')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('account');
    Route::get('/profile', [AccountController::class, 'profile'])->name('account.profile');
    Route::get('/contacts', [AccountController::class, 'contacts'])->name('account.contacts');
    Route::get('/roles/{assignment}', [AccountController::class, 'role'])->name('account.roles.show');

    Route::post('/moderation-request', [AccountModerationController::class, 'store'])->name('account.moderation.store');
    Route::patch('/profile', [AccountProfileController::class, 'update'])->name('account.profile.update');
    Route::patch('/password', [AccountProfileController::class, 'updatePassword'])->name('account.password.update');

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
    Route::patch('/{type}/{venue}', [VenuesController::class, 'update'])->middleware('auth')->name('venues.update');
    Route::post('/{type}/{venue}/moderation-request', [VenuesController::class, 'submitModerationRequest'])
        ->middleware('auth')
        ->name('venues.moderation.request');
    Route::get('/{type}', [VenuesController::class, 'type'])->name('venues.type');
});

Route::get('/integrations/address-suggest', AddressSuggestController::class)
    ->middleware('auth')
    ->name('integrations.address-suggest');

Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin');
    Route::get('/users-moderation', [AdminUsersModerationController::class, 'index'])
        ->name('admin.users.moderation');
    Route::post('/users-moderation/{moderationRequest}/approve', [AdminUsersModerationController::class, 'approve'])
        ->name('admin.users.moderation.approve');
    Route::post('/users-moderation/{moderationRequest}/reject', [AdminUsersModerationController::class, 'reject'])
        ->name('admin.users.moderation.reject');
    Route::post('/users-moderation/{moderationRequest}/block', [AdminUsersModerationController::class, 'block'])
        ->name('admin.users.moderation.block');
    Route::post('/users-moderation/{moderationRequest}/unblock', [AdminUsersModerationController::class, 'unblock'])
        ->name('admin.users.moderation.unblock');
    Route::get('/venues-moderation', [AdminVenuesModerationController::class, 'index'])
        ->name('admin.venues.moderation');
    Route::post('/venues-moderation/{moderationRequest}/approve', [AdminVenuesModerationController::class, 'approve'])
        ->name('admin.venues.moderation.approve');
    Route::post('/venues-moderation/{moderationRequest}/reject', [AdminVenuesModerationController::class, 'reject'])
        ->name('admin.venues.moderation.reject');
    Route::post('/venues-moderation/{moderationRequest}/block', [AdminVenuesModerationController::class, 'block'])
        ->name('admin.venues.moderation.block');
    Route::post('/venues-moderation/{moderationRequest}/unblock', [AdminVenuesModerationController::class, 'unblock'])
        ->name('admin.venues.moderation.unblock');
    Route::get('/logs', [AdminLogsController::class, 'index'])->name('admin.logs');
    Route::get('/logs/{entity}', [AdminLogsController::class, 'show'])->name('admin.logs.show');
});
