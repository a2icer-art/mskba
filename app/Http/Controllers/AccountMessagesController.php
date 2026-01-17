<?php

namespace App\Http\Controllers;

use App\Domain\Messages\Enums\MessagePrivacyMode;
use App\Domain\Messages\Models\Conversation;
use App\Domain\Messages\Models\MessageAllowList;
use App\Domain\Messages\Models\MessageBlockList;
use App\Domain\Messages\Models\MessagePrivacySetting;
use App\Domain\Messages\Services\ConversationService;
use App\Domain\Messages\Services\MessageCountersService;
use App\Domain\Messages\Services\MessagePrivacyService;
use App\Domain\Messages\Services\MessageQueryService;
use App\Domain\Messages\Services\MessageService;
use App\Presentation\Breadcrumbs\AccountBreadcrumbsPresenter;
use App\Presentation\Navigation\AccountNavigationPresenter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AccountMessagesController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $conversation = $this->resolveConversation($request, $user);
        $messagesService = app(MessageQueryService::class);

        $conversations = $messagesService->getConversations($user);
        $activeConversation = $conversation
            ? $this->presentConversation($conversation, $user)
            : null;
        $messages = $conversation
            ? $messagesService->getMessages($conversation, $user)
            : [];

        if ($conversation) {
            $messagesService->markConversationRead($conversation, $user);
        }

        $participantRoles = app(\App\Domain\Users\Services\AccountPageService::class)->getParticipantRoles($user);
        $navigation = $this->buildNavigation($participantRoles, $user);
        $breadcrumbs = app(AccountBreadcrumbsPresenter::class)->present([
            'activeTab' => 'messages',
            'participantRoles' => $participantRoles,
        ])['data'];

        return Inertia::render('AccountMessages', [
            'appName' => config('app.name'),
            'user' => [
                'id' => $user->id,
                'login' => $user->login,
            ],
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'messages' => $messages,
            'navigation' => $navigation,
            'activeHref' => '/account/messages',
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function settings(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $privacySetting = app(MessagePrivacyService::class)->getSettings($user);
        $allowList = MessageAllowList::query()
            ->where('owner_id', $user->id)
            ->with('allowedUser:id,login')
            ->orderBy('id')
            ->get()
            ->map(fn (MessageAllowList $item) => [
                'id' => $item->id,
                'user' => $item->allowedUser
                    ? [
                        'id' => $item->allowedUser->id,
                        'login' => $item->allowedUser->login,
                    ]
                    : null,
            ])
            ->values()
            ->all();
        $blockList = MessageBlockList::query()
            ->where('owner_id', $user->id)
            ->with('blockedUser:id,login')
            ->orderBy('id')
            ->get()
            ->map(fn (MessageBlockList $item) => [
                'id' => $item->id,
                'user' => $item->blockedUser
                    ? [
                        'id' => $item->blockedUser->id,
                        'login' => $item->blockedUser->login,
                    ]
                    : null,
            ])
            ->values()
            ->all();

        $participantRoles = app(\App\Domain\Users\Services\AccountPageService::class)->getParticipantRoles($user);
        $navigation = $this->buildNavigation($participantRoles, $user);
        $breadcrumbs = app(AccountBreadcrumbsPresenter::class)->present([
            'activeTab' => 'messages-settings',
            'participantRoles' => $participantRoles,
        ])['data'];

        return Inertia::render('AccountMessagesSettings', [
            'appName' => config('app.name'),
            'user' => [
                'id' => $user->id,
                'login' => $user->login,
            ],
            'privacySetting' => [
                'mode' => $privacySetting->mode?->value ?? MessagePrivacyMode::All->value,
            ],
            'privacyOptions' => collect(MessagePrivacyMode::cases())
                ->map(fn (MessagePrivacyMode $mode) => [
                    'value' => $mode->value,
                    'label' => $mode->label(),
                ])
                ->all(),
            'allowList' => $allowList,
            'blockList' => $blockList,
            'navigation' => $navigation,
            'activeHref' => '/account/settings/messages',
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    public function poll(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $messagesService = app(MessageQueryService::class);
        $countersService = app(MessageCountersService::class);

        $payload = [
            'unread_messages' => $countersService->getUnreadMessages($user),
        ];

        if ($request->boolean('include_conversations')) {
            $payload['conversations'] = $messagesService->getConversations($user);
        }

        $conversationId = $request->integer('conversation_id');
        if ($conversationId) {
            $conversation = Conversation::query()
                ->whereKey($conversationId)
                ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id))
                ->first();

            if ($conversation) {
                $payload['active_conversation'] = $this->presentConversation($conversation, $user);
                $payload['messages'] = $messagesService->getMessages($conversation, $user);
            }
        }

        return response()->json($payload);
    }

    public function startConversation(Request $request, ConversationService $service, MessagePrivacyService $privacyService)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $recipient = User::query()->find($data['user_id']);
        if (!$recipient) {
            throw ValidationException::withMessages(['recipient_id' => 'Пользователь не найден.']);
        }

        $privacyService->ensureCanSend($user, $recipient);

        $conversation = $service->findOrCreateDirect($user, $recipient);

        return response()->json([
            'conversation_id' => $conversation->id,
        ]);
    }

    public function sendMessage(
        Request $request,
        Conversation $conversation,
        MessageService $service,
        MessagePrivacyService $privacyService
    ) {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $participants = $conversation->participants()->pluck('user_id')->all();
        if (!in_array($user->id, $participants, true)) {
            abort(403);
        }

        $recipientId = collect($participants)->first(fn ($id) => $id !== $user->id);
        if ($recipientId) {
            $recipient = User::query()->find($recipientId);
            if ($recipient) {
                $privacyService->ensureCanSend($user, $recipient);
            }
        }

        $message = $service->send($conversation, $user, $data['body']);

        return response()->json([
            'message_id' => $message->id,
        ]);
    }

    public function markRead(Request $request, Conversation $conversation, MessageQueryService $service)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $isParticipant = $conversation->participants()->where('user_id', $user->id)->exists();
        if (!$isParticipant) {
            abort(403);
        }

        $service->markConversationRead($conversation, $user);

        return response()->json([
            'success' => true,
        ]);
    }

    public function deleteMessage(Request $request, \App\Domain\Messages\Models\Message $message, MessageQueryService $service)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $deleted = $service->deleteMessage($message, $user);

        return response()->json([
            'deleted' => $deleted,
        ]);
    }

    public function updateSettings(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $data = $request->validate([
            'mode' => ['required', 'string', Rule::in(array_map(fn ($mode) => $mode->value, MessagePrivacyMode::cases()))],
        ]);

        MessagePrivacySetting::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['mode' => $data['mode']]
        );

        return back()->with('notice', 'Настройки сообщений обновлены.');
    }

    public function storeAllowList(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        MessageAllowList::query()->firstOrCreate([
            'owner_id' => $user->id,
            'allowed_user_id' => $data['user_id'],
        ]);

        return back()->with('notice', 'Пользователь добавлен в разрешенные.');
    }

    public function destroyAllowList(Request $request, User $user)
    {
        $owner = $request->user();
        if (!$owner) {
            abort(403);
        }

        MessageAllowList::query()
            ->where('owner_id', $owner->id)
            ->where('allowed_user_id', $user->id)
            ->delete();

        return back()->with('notice', 'Пользователь удален из разрешенных.');
    }

    public function storeBlockList(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        MessageBlockList::query()->firstOrCreate([
            'owner_id' => $user->id,
            'blocked_user_id' => $data['user_id'],
        ]);

        return back()->with('notice', 'Пользователь добавлен в черный список.');
    }

    public function destroyBlockList(Request $request, User $user)
    {
        $owner = $request->user();
        if (!$owner) {
            abort(403);
        }

        MessageBlockList::query()
            ->where('owner_id', $owner->id)
            ->where('blocked_user_id', $user->id)
            ->delete();

        return back()->with('notice', 'Пользователь удален из черного списка.');
    }

    private function resolveConversation(Request $request, User $user): ?Conversation
    {
        $conversationId = $request->integer('conversation');
        $targetUserId = $request->integer('user_id');

        if ($targetUserId) {
            $target = User::query()->find($targetUserId);
            if ($target) {
                return app(ConversationService::class)->findOrCreateDirect($user, $target);
            }
        }

        if ($conversationId) {
            return Conversation::query()
                ->whereKey($conversationId)
                ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id))
                ->first();
        }

        return null;
    }

    private function presentConversation(Conversation $conversation, User $user): array
    {
        $conversation->loadMissing(['participants.user:id,login']);
        $otherParticipant = $conversation->participants
            ->first(fn ($participant) => $participant->user_id !== $user->id);
        $otherUser = $otherParticipant?->user;

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'title' => $otherUser?->login ?? 'Диалог',
            'other_user' => $otherUser
                ? [
                    'id' => $otherUser->id,
                    'login' => $otherUser->login,
                ]
                : null,
        ];
    }

    private function buildNavigation(array $participantRoles, User $user): array
    {
        return app(AccountNavigationPresenter::class)->present([
            'participantRoles' => $participantRoles,
            'messageCounters' => [
                'unread_messages' => app(MessageCountersService::class)->getUnreadMessages($user),
            ],
        ]);
    }
}
