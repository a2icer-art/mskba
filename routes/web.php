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
use App\Http\Controllers\Integrations\UserSuggestController;
use App\Http\Controllers\Integrations\VenueSuggestController;
use App\Http\Controllers\EventsController;
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
    Route::get('/access', [AccountController::class, 'access'])->name('account.access');
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
    Route::post('/', [VenuesController::class, 'store'])
        ->middleware(['auth', 'can:venue.create'])
        ->name('venues.store');
    Route::patch('/{type}/{venue}', [VenuesController::class, 'update'])
        ->middleware(['auth', 'can:update,venue'])
        ->name('venues.update');
    Route::post('/{type}/{venue}/moderation-request', [VenuesController::class, 'submitModerationRequest'])
        ->middleware('auth')
        ->name('venues.moderation.request');
    Route::get('/{type}/{venue}/schedule', [VenuesController::class, 'schedule'])
        ->middleware('auth')
        ->name('venues.schedule');
    Route::post('/{type}/{venue}/schedule/intervals', [VenuesController::class, 'storeScheduleInterval'])
        ->middleware('auth')
        ->name('venues.schedule.intervals.store');
    Route::patch('/{type}/{venue}/schedule/intervals/{interval}', [VenuesController::class, 'updateScheduleInterval'])
        ->middleware('auth')
        ->name('venues.schedule.intervals.update');
    Route::delete('/{type}/{venue}/schedule/intervals/{interval}', [VenuesController::class, 'destroyScheduleInterval'])
        ->middleware('auth')
        ->name('venues.schedule.intervals.destroy');
    Route::post('/{type}/{venue}/schedule/exceptions', [VenuesController::class, 'storeScheduleException'])
        ->middleware('auth')
        ->name('venues.schedule.exceptions.store');
    Route::patch('/{type}/{venue}/schedule/exceptions/{exception}', [VenuesController::class, 'updateScheduleException'])
        ->middleware('auth')
        ->name('venues.schedule.exceptions.update');
    Route::delete('/{type}/{venue}/schedule/exceptions/{exception}', [VenuesController::class, 'destroyScheduleException'])
        ->middleware('auth')
        ->name('venues.schedule.exceptions.destroy');
    Route::get('/{type}/{venue}/contracts', [VenuesController::class, 'contracts'])
        ->middleware('auth')
        ->name('venues.contracts');
    Route::post('/{type}/{venue}/contracts', [VenuesController::class, 'assignContract'])
        ->middleware('auth')
        ->name('venues.contracts.assign');
    Route::patch('/{type}/{venue}/contracts/{contract}/permissions', [VenuesController::class, 'updateContractPermissions'])
        ->middleware('auth')
        ->name('venues.contracts.permissions.update');
    Route::post('/{type}/{venue}/contracts/{contract}/revoke', [VenuesController::class, 'revokeContract'])
        ->middleware('auth')
        ->name('venues.contracts.revoke');
    Route::get('/{type}', [VenuesController::class, 'type'])->name('venues.type');
});

Route::get('/integrations/address-suggest', AddressSuggestController::class)
    ->middleware('auth')
    ->name('integrations.address-suggest');
Route::get('/integrations/user-suggest', UserSuggestController::class)
    ->middleware('auth')
    ->name('integrations.user-suggest');
Route::get('/integrations/venue-suggest', VenueSuggestController::class)
    ->middleware('auth')
    ->name('integrations.venue-suggest');

Route::get('/events', [EventsController::class, 'index'])
    ->name('events.index');
Route::post('/events', [EventsController::class, 'store'])
    ->middleware(['auth', 'can:event.create'])
    ->name('events.store');
Route::get('/events/{event}', [EventsController::class, 'show'])
    ->name('events.show');
Route::delete('/events/{event}', [EventsController::class, 'destroy'])
    ->middleware('auth')
    ->name('events.destroy');
Route::post('/events/{event}/bookings', [EventsController::class, 'storeBooking'])
    ->middleware(['auth', 'can:venue.booking'])
    ->name('events.bookings.store');

Route::middleware(['auth', 'can:moderation.access', 'confirmed.role:10'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('admin');
    Route::get('/users-moderation', [AdminUsersModerationController::class, 'index'])
        ->name('admin.users.moderation');
    Route::post('/users-moderation/{moderationRequest}/approve', [AdminUsersModerationController::class, 'approve'])
        ->name('admin.users.moderation.approve');
    Route::post('/users-moderation/{moderationRequest}/permissions', [AdminUsersModerationController::class, 'updatePermissions'])
        ->name('admin.users.moderation.permissions');
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
    Route::get('/logs', [AdminLogsController::class, 'index'])
        ->middleware('can:logs.view')
        ->name('admin.logs');
    Route::get('/logs/{entity}', [AdminLogsController::class, 'show'])
        ->middleware('can:logs.view')
        ->name('admin.logs.show');
});
