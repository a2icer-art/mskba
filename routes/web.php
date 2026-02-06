<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountProfileController;
use App\Http\Controllers\AccountContactsController;
use App\Http\Controllers\AccountModerationController;
use App\Http\Controllers\AccountMessagesController;
use App\Http\Controllers\AccountNotificationsController;
use App\Http\Controllers\AccountPaymentInfoController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AdminBalancesController;
use App\Http\Controllers\AdminContractsModerationController;
use App\Http\Controllers\AdminEventsController;
use App\Http\Controllers\AdminLogsController;
use App\Http\Controllers\AdminSeoController;
use App\Http\Controllers\AdminSettingsController;
use App\Http\Controllers\AdminUsersController;
use App\Http\Controllers\AdminUsersModerationController;
use App\Http\Controllers\AdminVenuesModerationController;
use App\Http\Controllers\AdminVenuesController;
use App\Http\Controllers\AuthTelegramController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Integrations\AddressSuggestController;
use App\Http\Controllers\Integrations\UserSuggestController;
use App\Http\Controllers\Integrations\VenueSuggestController;
use App\Http\Controllers\Integrations\TelegramWebhookController;
use App\Http\Controllers\EventsController;
use App\Http\Controllers\VenuesController;
use App\Http\Controllers\MediaController;
use Illuminate\Http\Request;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/media', function () {
    return Inertia::render('MediaPublic', [
        'appName' => config('app.name'),
    ]);
})->name('media.index');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.store');
Route::post('/register', [AuthController::class, 'register'])->name('register.store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::post('/auth/telegram/token', [AuthTelegramController::class, 'createToken'])->name('auth.telegram.token');
Route::get('/auth/telegram/complete', [AuthTelegramController::class, 'complete'])->name('auth.telegram.complete');
Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->withoutMiddleware([VerifyCsrfToken::class])
    ->name('telegram.webhook');
Route::get('/csrf-token', function (Request $request) {
    $request->session()->regenerateToken();

    return response()->json([
        'csrfToken' => csrf_token(),
    ]);
})->name('csrf.token');
Route::get('/session/ping', function () {
    return response()->noContent();
})->middleware('auth')->name('session.ping');

Broadcast::routes(['middleware' => ['web', 'auth']]);

Route::middleware('auth')->prefix('account')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('account');
    Route::get('/profile', [AccountController::class, 'profile'])->name('account.profile');
    Route::get('/contacts', [AccountController::class, 'contacts'])->name('account.contacts');
    Route::get('/access', [AccountController::class, 'access'])->name('account.access');
    Route::get('/balance', [AccountController::class, 'balance'])->name('account.balance');
    Route::get('/messages', [AccountMessagesController::class, 'index'])->name('account.messages');
    Route::get('/settings/messages', [AccountMessagesController::class, 'settings'])
        ->name('account.settings.messages');
    Route::get('/settings/notifications', [AccountNotificationsController::class, 'settings'])
        ->name('account.settings.notifications');
    Route::patch('/settings/notifications', [AccountNotificationsController::class, 'update'])
        ->name('account.settings.notifications.update');
    Route::get('/settings/payment-info', [AccountPaymentInfoController::class, 'index'])
        ->name('account.settings.payment-info');
    Route::post('/settings/payment-info', [AccountPaymentInfoController::class, 'store'])
        ->name('account.settings.payment-info.store');
    Route::patch('/settings/payment-info/{paymentMethod}', [AccountPaymentInfoController::class, 'update'])
        ->name('account.settings.payment-info.update');
    Route::delete('/settings/payment-info/{paymentMethod}', [AccountPaymentInfoController::class, 'destroy'])
        ->name('account.settings.payment-info.destroy');
    Route::get('/roles/{alias}', [AccountController::class, 'role'])->name('account.roles.show');

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

    Route::prefix('messages')->name('account.messages.')->group(function () {
        Route::get('/poll', [AccountMessagesController::class, 'poll'])->name('poll');
        Route::post('/conversations', [AccountMessagesController::class, 'startConversation'])->name('conversations.start');
        Route::post('/direct', [AccountMessagesController::class, 'sendDirect'])->name('direct.send');
        Route::post('/conversations/{conversation}/messages', [AccountMessagesController::class, 'sendMessage'])
            ->name('conversations.messages.store');
        Route::post('/conversations/{conversation}/read', [AccountMessagesController::class, 'markRead'])
            ->name('conversations.read');
        Route::post('/messages/{message}/delete', [AccountMessagesController::class, 'deleteMessage'])
            ->name('messages.delete');
    });
    Route::prefix('settings/messages')->name('account.messages.settings.')->group(function () {
        Route::patch('/', [AccountMessagesController::class, 'updateSettings'])->name('update');
        Route::post('/allow-list', [AccountMessagesController::class, 'storeAllowList'])->name('allow.store');
        Route::delete('/allow-list/{user}', [AccountMessagesController::class, 'destroyAllowList'])->name('allow.destroy');
        Route::post('/block-list', [AccountMessagesController::class, 'storeBlockList'])->name('block.store');
        Route::delete('/block-list/{user}', [AccountMessagesController::class, 'destroyBlockList'])->name('block.destroy');
    });

});

Route::prefix('venues')->group(function () {
    Route::get('/', [VenuesController::class, 'index'])->name('venues');
    Route::get('/schedule-day/{venueId}', [VenuesController::class, 'scheduleDayById'])
        ->name('venues.schedule.day.by-id');
    Route::get('/schedule-day-bookings/{venueId}', [VenuesController::class, 'scheduleDayBookingsById'])
        ->name('venues.schedule.day-bookings.by-id');
    Route::get('/{type}/{venue}', [VenuesController::class, 'show'])->name('venues.show');
    Route::get('/{type}/{venue}/feed', [VenuesController::class, 'feed'])->name('venues.feed');
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
        ->name('venues.schedule');
    Route::get('/{type}/{venue}/admin', [VenuesController::class, 'adminOverview'])
        ->middleware('auth')
        ->name('venues.admin');
    Route::get('/{type}/{venue}/admin/schedule', [VenuesController::class, 'adminSchedule'])
        ->middleware('auth')
        ->name('venues.schedule.admin');
    Route::get('/{type}/{venue}/schedule-day', [VenuesController::class, 'scheduleDay'])
        ->name('venues.schedule.day');
    Route::post('/{type}/{venue}/schedule/intervals', [VenuesController::class, 'storeScheduleInterval'])
        ->middleware('auth')
        ->name('venues.schedule.intervals.store');
    Route::get('/{type}/{venue}/schedule-day-bookings', [VenuesController::class, 'scheduleDayBookings'])
        ->name('venues.schedule.day-bookings');
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
    Route::get('/{type}/{venue}/admin/contracts', [VenuesController::class, 'contracts'])
        ->middleware('auth')
        ->name('venues.contracts');
    Route::post('/{type}/{venue}/admin/contracts', [VenuesController::class, 'assignContract'])
        ->middleware('auth')
        ->name('venues.contracts.assign');
    Route::post('/{type}/{venue}/admin/contracts/moderation', [VenuesController::class, 'submitContractModeration'])
        ->middleware('auth')
        ->name('venues.contracts.moderation');
    Route::patch('/{type}/{venue}/admin/contracts/{contract}/permissions', [VenuesController::class, 'updateContractPermissions'])
        ->middleware('auth')
        ->name('venues.contracts.permissions.update');
    Route::post('/{type}/{venue}/admin/contracts/{contract}/payment-methods', [VenuesController::class, 'storeContractPaymentMethod'])
        ->middleware('auth')
        ->name('venues.contracts.payment-methods.store');
    Route::patch('/{type}/{venue}/admin/contracts/{contract}/payment-methods/{paymentMethod}', [VenuesController::class, 'updateContractPaymentMethod'])
        ->middleware('auth')
        ->name('venues.contracts.payment-methods.update');
    Route::delete('/{type}/{venue}/admin/contracts/{contract}/payment-methods/{paymentMethod}', [VenuesController::class, 'destroyContractPaymentMethod'])
        ->middleware('auth')
        ->name('venues.contracts.payment-methods.destroy');
    Route::post('/{type}/{venue}/admin/contracts/{contract}/revoke', [VenuesController::class, 'revokeContract'])
        ->middleware('auth')
        ->name('venues.contracts.revoke');
    Route::get('/{type}/{venue}/admin/bookings', [VenuesController::class, 'bookings'])
        ->middleware('auth')
        ->name('venues.bookings');
    Route::post('/{type}/{venue}/admin/bookings/{booking}/confirm', [VenuesController::class, 'confirmBooking'])
        ->middleware('auth')
        ->name('venues.bookings.confirm');
    Route::post('/{type}/{venue}/admin/bookings/{booking}/await-payment', [VenuesController::class, 'awaitPaymentBooking'])
        ->middleware('auth')
        ->name('venues.bookings.await-payment');
    Route::post('/{type}/{venue}/admin/bookings/{booking}/mark-paid', [VenuesController::class, 'markPaidBooking'])
        ->middleware('auth')
        ->name('venues.bookings.mark-paid');
    Route::post('/{type}/{venue}/admin/bookings/{booking}/cancel', [VenuesController::class, 'cancelBooking'])
        ->middleware('auth')
        ->name('venues.bookings.cancel');
    Route::get('/{type}/{venue}/admin/supervisor', [VenuesController::class, 'supervisor'])
        ->middleware('auth')
        ->name('venues.supervisor');
    Route::patch('/{type}/{venue}/admin/supervisor', [VenuesController::class, 'updateSupervisor'])
        ->middleware('auth')
        ->name('venues.supervisor.update');
    Route::get('/{type}/{venue}/admin/settings', [VenuesController::class, 'settings'])
        ->middleware('auth')
        ->name('venues.settings');
    Route::patch('/{type}/{venue}/admin/settings', [VenuesController::class, 'updateSettings'])
        ->middleware('auth')
        ->name('venues.settings.update');
    Route::post('/{type}/{venue}/admin/settings/payment-methods', [VenuesController::class, 'storeVenuePaymentMethod'])
        ->middleware('auth')
        ->name('venues.settings.payment-methods.store');
    Route::patch('/{type}/{venue}/admin/settings/payment-methods/{paymentMethod}', [VenuesController::class, 'updateVenuePaymentMethod'])
        ->middleware('auth')
        ->name('venues.settings.payment-methods.update');
    Route::delete('/{type}/{venue}/admin/settings/payment-methods/{paymentMethod}', [VenuesController::class, 'destroyVenuePaymentMethod'])
        ->middleware('auth')
        ->name('venues.settings.payment-methods.destroy');
    Route::post('/{type}/{venue}/admin/settings/amenities/{amenity}/icon', [VenuesController::class, 'uploadCustomAmenityIcon'])
        ->middleware('auth')
        ->name('venues.settings.amenities.icon');
    Route::get('/{type}/{venue}/admin/media', [VenuesController::class, 'media'])
        ->middleware('auth')
        ->name('venues.media');
    Route::get('/{type}/{venue}/media', [VenuesController::class, 'mediaPublic'])
        ->name('venues.media.public');
    Route::post('/{type}/{venue}/media', [MediaController::class, 'store'])
        ->middleware('auth')
        ->name('venues.media.store');
    Route::patch('/{type}/{venue}/media/{media}', [MediaController::class, 'update'])
        ->middleware('auth')
        ->name('venues.media.update');
    Route::delete('/{type}/{venue}/media/{media}', [MediaController::class, 'destroy'])
        ->middleware('auth')
        ->name('venues.media.destroy');
    Route::post('/{type}/{venue}/media/{media}/restore', [MediaController::class, 'restore'])
        ->middleware('auth')
        ->name('venues.media.restore');
    Route::delete('/{type}/{venue}/media/{media}/force', [MediaController::class, 'forceDestroy'])
        ->middleware('auth')
        ->name('venues.media.force_destroy');
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
Route::get('/events/create-modal', [EventsController::class, 'createModal'])
    ->middleware('auth')
    ->name('events.create-modal');
Route::post('/events', [EventsController::class, 'store'])
    ->middleware(['auth', 'can:event.create'])
    ->name('events.store');
Route::get('/events/{event}', [EventsController::class, 'show'])
    ->name('events.show');
Route::patch('/events/{event}', [EventsController::class, 'update'])
    ->middleware('auth')
    ->name('events.update');
Route::delete('/events/{event}', [EventsController::class, 'destroy'])
    ->middleware('auth')
    ->name('events.destroy');
Route::post('/events/{event}/bookings', [EventsController::class, 'storeBooking'])
    ->middleware(['auth', 'can:venue.booking'])
    ->name('events.bookings.store');
Route::post('/events/{event}/bookings/{booking}/payment-confirmations', [EventsController::class, 'requestPaymentConfirmation'])
    ->middleware('auth')
    ->name('events.bookings.payment-confirmations.store');
Route::post('/events/{event}/bookings/{booking}/payment-confirmations/{confirmation}/approve', [EventsController::class, 'approvePaymentConfirmation'])
    ->middleware('auth')
    ->name('events.bookings.payment-confirmations.approve');
Route::post('/events/{event}/bookings/{booking}/payment-confirmations/{confirmation}/reject', [EventsController::class, 'rejectPaymentConfirmation'])
    ->middleware('auth')
    ->name('events.bookings.payment-confirmations.reject');
Route::post('/events/{event}/participants/invite', [EventsController::class, 'inviteParticipant'])
    ->middleware('auth')
    ->name('events.participants.invite');
Route::post('/events/{event}/participants/join', [EventsController::class, 'joinEvent'])
    ->middleware('auth')
    ->name('events.participants.join');
Route::post('/events/{event}/participants/{participant}/respond', [EventsController::class, 'respondParticipant'])
    ->middleware('auth')
    ->name('events.participants.respond');
Route::post('/events/{event}/participants/{participant}/status', [EventsController::class, 'updateParticipantStatus'])
    ->middleware('auth')
    ->name('events.participants.status');

Route::middleware(['auth', 'can:seo.manage', 'confirmed.role:10'])->prefix('admin')->group(function () {
    Route::get('/seo', [AdminSeoController::class, 'index'])->name('admin.seo');
    Route::patch('/seo', [AdminSeoController::class, 'update'])->name('admin.seo.update');
    Route::post('/seo/bulk', [AdminSeoController::class, 'bulkUpdate'])->name('admin.seo.bulk');
    Route::post('/seo/favicon', [AdminSeoController::class, 'uploadFavicon'])->name('admin.seo.favicon');
    Route::patch('/seo/settings', [AdminSeoController::class, 'updateMetaSettings'])->name('admin.seo.settings');
});

Route::middleware(['auth', 'can:admin.access', 'confirmed.role:10'])->prefix('admin')->group(function () {
    Route::get('/venues', [AdminVenuesController::class, 'index'])->name('admin.venues');
    Route::post('/venues/amenities', [AdminVenuesController::class, 'storeAmenity'])
        ->name('admin.venues.amenities.store');
    Route::patch('/venues/amenities/{amenity}', [AdminVenuesController::class, 'updateAmenity'])
        ->name('admin.venues.amenities.update');
    Route::delete('/venues/amenities/{amenity}', [AdminVenuesController::class, 'destroyAmenity'])
        ->name('admin.venues.amenities.destroy');
    Route::post('/venues/amenities/{amenity}/icon', [AdminVenuesController::class, 'uploadAmenityIcon'])
        ->name('admin.venues.amenities.icon');
    Route::get('/users', [AdminUsersController::class, 'index'])->name('admin.users');
    Route::post('/users/{user}/contacts/{contact}/reset-confirmation', [AdminUsersController::class, 'resetContactConfirmation'])
        ->name('admin.users.contacts.reset');
    Route::post('/users/{user}/roles', [AdminUsersController::class, 'updateRoles'])
        ->name('admin.users.roles.update');
    Route::post('/users/{user}/participant-roles', [AdminUsersController::class, 'updateParticipantRoles'])
        ->name('admin.users.participant-roles.update');
});

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
    Route::get('/contracts-moderation', [AdminContractsModerationController::class, 'index'])
        ->name('admin.contracts.moderation');
    Route::post('/contracts-moderation/{moderationRequest}/approve', [AdminContractsModerationController::class, 'approve'])
        ->name('admin.contracts.moderation.approve');
    Route::post('/contracts-moderation/{moderationRequest}/reject', [AdminContractsModerationController::class, 'reject'])
        ->name('admin.contracts.moderation.reject');
    Route::post('/contracts-moderation/{moderationRequest}/clarify', [AdminContractsModerationController::class, 'clarify'])
        ->name('admin.contracts.moderation.clarify');
    Route::post('/contracts-moderation/{moderationRequest}/contracts/{contract}/revoke', [AdminContractsModerationController::class, 'revoke'])
        ->name('admin.contracts.moderation.revoke');
    Route::post('/contracts-moderation/{moderationRequest}/contracts/{contract}/restore', [AdminContractsModerationController::class, 'restore'])
        ->name('admin.contracts.moderation.restore');
    Route::post('/contracts-moderation/{moderationRequest}/permissions', [AdminContractsModerationController::class, 'updatePermissions'])
        ->name('admin.contracts.moderation.permissions');
    Route::get('/logs', [AdminLogsController::class, 'index'])
        ->middleware(['can:admin.access', 'can:logs.view'])
        ->name('admin.logs');
    Route::get('/logs/export', [AdminLogsController::class, 'export'])
        ->middleware(['can:admin.access', 'can:logs.view'])
        ->name('admin.logs.export');
    Route::post('/logs/export-delete', [AdminLogsController::class, 'exportAndDelete'])
        ->middleware(['can:admin.access', 'can:logs.view'])
        ->name('admin.logs.export-delete');
    Route::delete('/logs', [AdminLogsController::class, 'destroy'])
        ->middleware(['can:admin.access', 'can:logs.view'])
        ->name('admin.logs.destroy');
    Route::get('/logs/{entity}', [AdminLogsController::class, 'show'])
        ->middleware(['can:admin.access', 'can:logs.view'])
        ->name('admin.logs.show');
    Route::get('/settings', [AdminSettingsController::class, 'index'])
        ->middleware('can:admin.access')
        ->name('admin.settings');
    Route::patch('/settings', [AdminSettingsController::class, 'update'])
        ->middleware('can:admin.access')
        ->name('admin.settings.update');
    Route::post('/settings/avatar-placeholder', [AdminSettingsController::class, 'uploadAvatarPlaceholder'])
        ->middleware('can:admin.access')
        ->name('admin.settings.avatar-placeholder');
    Route::post('/settings/test-email', [AdminSettingsController::class, 'testEmail'])
        ->middleware('can:admin.access')
        ->name('admin.settings.test-email');
    Route::get('/events', [AdminEventsController::class, 'index'])
        ->middleware('can:admin.access')
        ->name('admin.events');
    Route::patch('/events', [AdminEventsController::class, 'update'])
        ->middleware('can:admin.access')
        ->name('admin.events.update');
    Route::get('/balances', [AdminBalancesController::class, 'index'])
        ->middleware('can:admin.access')
        ->name('admin.balances');
    Route::post('/balances/{user}/top-up', [AdminBalancesController::class, 'topUp'])
        ->middleware('can:admin.access')
        ->name('admin.balances.topup');
    Route::post('/balances/{user}/debit', [AdminBalancesController::class, 'debit'])
        ->middleware('can:admin.access')
        ->name('admin.balances.debit');
    Route::post('/balances/{user}/block', [AdminBalancesController::class, 'block'])
        ->middleware('can:admin.access')
        ->name('admin.balances.block');
    Route::post('/balances/{user}/unblock', [AdminBalancesController::class, 'unblock'])
        ->middleware('can:admin.access')
        ->name('admin.balances.unblock');
});

Route::fallback(function (Request $request) {
    return Inertia::render('Error', [
        'status' => 404,
        'message' => 'Страница не найдена.',
        'appName' => config('app.name'),
    ])->toResponse($request)->setStatusCode(404);
});
