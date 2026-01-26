<?php

namespace App\Http\Controllers;

use App\Domain\Notifications\Services\NotificationSettingsService;
use App\Domain\Users\Enums\ContactType;
use App\Presentation\Breadcrumbs\AccountBreadcrumbsPresenter;
use App\Presentation\Navigation\AccountNavigationPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AccountNotificationsController extends Controller
{
    public function settings(Request $request, NotificationSettingsService $settingsService)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $contacts = $user->contacts()
            ->orderByDesc('confirmed_at')
            ->orderByDesc('id')
            ->get(['id', 'type', 'value', 'confirmed_at']);

        $contactMap = [];
        foreach (ContactType::cases() as $type) {
            $contactMap[$type->value] = $contacts
                ->where('type', $type)
                ->map(fn ($contact) => [
                    'id' => $contact->id,
                    'value' => $contact->value,
                    'confirmed' => (bool) $contact->confirmed_at,
                ])
                ->values()
                ->all();
        }

        $participantRoles = app(\App\Domain\Users\Services\AccountPageService::class)->getParticipantRoles($user);
        $navigation = app(AccountNavigationPresenter::class)->present([
            'participantRoles' => $participantRoles,
            'messageCounters' => [
                'unread_messages' => app(\App\Domain\Messages\Services\MessageCountersService::class)->getUnreadMessages($user),
            ],
        ]);
        $breadcrumbs = app(AccountBreadcrumbsPresenter::class)->present([
            'activeTab' => 'notifications-settings',
            'participantRoles' => $participantRoles,
        ])['data'];

        return Inertia::render('AccountNotificationsSettings', [
            'appName' => config('app.name'),
            'user' => [
                'id' => $user->id,
                'login' => $user->login,
            ],
            'definitions' => $settingsService->getDefinitions(),
            'settings' => $settingsService->getForUser($user),
            'channels' => $settingsService->getChannelOptions(),
            'contacts' => $contactMap,
            'navigation' => $navigation,
            'activeHref' => '/account/settings/notifications',
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function update(Request $request, NotificationSettingsService $settingsService)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $data = $request->validate([
            'notifications' => ['required', 'array'],
        ]);

        $settingsService->updateForUser($user, $data['notifications']);

        return back()->with('notice', 'Настройки уведомлений обновлены.');
    }
}
