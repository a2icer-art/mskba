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
use App\Domain\Messages\Services\MessageRealtimeService;
use App\Domain\Messages\Services\MessageService;
use App\Domain\Contracts\Enums\ContractStatus;
use App\Domain\Contracts\Models\Contract;
use App\Domain\Events\Models\EventBooking;
use App\Domain\Permissions\Enums\PermissionCode;
use App\Presentation\Breadcrumbs\AccountBreadcrumbsPresenter;
use App\Presentation\Navigation\AccountNavigationPresenter;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        if (!$conversation && !empty($conversations)) {
            $sorted = collect($conversations)->sortByDesc(function (array $item) {
                return $item['last_message']['created_at']
                    ?? $item['updated_at']
                    ?? '1970-01-01 00:00:00';
            });
            $firstId = $sorted->first()['id'] ?? null;
            if ($firstId) {
                $conversation = Conversation::query()
                    ->whereKey($firstId)
                    ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id))
                    ->first();
            }
        }
        $activeConversation = $conversation
            ? $this->presentConversation($conversation, $user)
            : null;
        $messagesPayload = $conversation
            ? $messagesService->getMessages($conversation, $user, 10)
            : ['messages' => [], 'meta' => ['has_more' => false, 'oldest_id' => null]];
        $messages = $messagesPayload['messages'];
        $messagesMeta = $messagesPayload['meta'];
        if ($conversation) {
            $updated = $messagesService->markConversationRead($conversation, $user);
            if ($updated > 0) {
                app(MessageRealtimeService::class)->broadcastConversationRead($conversation, $user);
            }
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
            'messagesMeta' => $messagesMeta,
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
                $limit = max(1, min(50, (int) $request->integer('messages_limit', 10)));
                $beforeId = $request->integer('messages_before_id') ?: null;
                $afterId = $request->integer('messages_after_id') ?: null;
                if ($beforeId) {
                    $afterId = null;
                }
                $messagesPayload = $messagesService->getMessages($conversation, $user, $limit, $beforeId, $afterId);
                $payload['messages'] = $messagesPayload['messages'];
                if (array_key_exists('meta', $messagesPayload) && $messagesPayload['meta'] !== null) {
                    $payload['messages_meta'] = $messagesPayload['meta'];
                }
                if ($beforeId) {
                    $payload['messages_mode'] = 'prepend';
                } elseif ($afterId) {
                    $payload['messages_mode'] = 'append';
                    $refreshPayload = $messagesService->getMessages($conversation, $user, 10);
                    $payload['messages_refresh'] = $refreshPayload['messages'];
                } else {
                    $payload['messages_mode'] = 'replace';
                }
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

        $rateKey = "messages:send:conversation:{$conversation->id}:{$user->id}";
        if (RateLimiter::tooManyAttempts($rateKey, 1)) {
            throw ValidationException::withMessages([
                'message' => 'Сообщение можно отправлять не чаще одного раза в секунду.',
            ]);
        }
        RateLimiter::hit($rateKey, 2);

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

    public function sendDirect(
        Request $request,
        ConversationService $conversationService,
        MessageService $messageService,
        MessagePrivacyService $privacyService
    ) {
        $user = $request->user();
        if (!$user) {
            abort(403);
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $recipient = User::query()->find($data['user_id']);
        if (!$recipient) {
            throw ValidationException::withMessages(['recipient_id' => 'Пользователь не найден.']);
        }

        $rateKey = "messages:send:direct:{$user->id}:{$recipient->id}";
        if (RateLimiter::tooManyAttempts($rateKey, 1)) {
            throw ValidationException::withMessages([
                'message' => 'Сообщение можно отправлять не чаще одного раза в секунду.',
            ]);
        }
        RateLimiter::hit($rateKey, 2);

        $privacyService->ensureCanSend($user, $recipient);

        $conversation = $conversationService->findOrCreateDirect($user, $recipient);
        $message = $messageService->send($conversation, $user, $data['body']);

        return response()->json([
            'conversation_id' => $conversation->id,
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

        $updated = $service->markConversationRead($conversation, $user);
        if ($updated > 0) {
            app(MessageRealtimeService::class)->broadcastConversationRead($conversation, $user);
        }

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
        $isSystem = $conversation->type === 'system';
        $title = $isSystem ? ($conversation->context_label ?? 'Системное уведомление') : ($otherUser?->login ?? 'Диалог');
        $contacts = $isSystem
            ? $this->resolveSystemContacts($conversation, $user)
            : $conversation->participants
                ->map(fn ($participant) => $participant->user
                    ? ['id' => $participant->user->id, 'login' => $participant->user->login]
                    : null
                )
                ->filter()
                ->values()
                ->all();

        return [
            'id' => $conversation->id,
            'type' => $conversation->type,
            'title' => $title,
            'other_user' => $otherUser
                ? [
                    'id' => $otherUser->id,
                    'login' => $otherUser->login,
                ]
                : null,
            'contacts' => $contacts,
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

    private function resolveSystemContacts(Conversation $conversation, User $user): array
    {
        if ($conversation->context_type !== EventBooking::class || !$conversation->context_id) {
            return [];
        }

        $booking = EventBooking::query()
            ->whereKey($conversation->context_id)
            ->with([
                'creator:id,login',
                'venue:id,name',
            ])
            ->first();

        if (!$booking) {
            return [];
        }

        if ($booking->created_by === $user->id) {
            $venue = $booking->venue;
            if (!$venue) {
                return [];
            }

            $permissionCodes = [
                PermissionCode::VenueBookingConfirm->value,
                PermissionCode::VenueBookingCancel->value,
            ];

            $contracts = Contract::query()
                ->where('entity_type', $venue->getMorphClass())
                ->where('entity_id', $venue->id)
                ->where('status', ContractStatus::Active->value)
                ->whereHas('permissions', function ($query) use ($permissionCodes) {
                    $query->whereIn('permissions.code', $permissionCodes)
                        ->where('contract_permissions.is_active', true);
                })
                ->with('user:id,login')
                ->get();

            if ($contracts->isEmpty()) {
                $contracts = Contract::query()
                    ->where('entity_type', $venue->getMorphClass())
                    ->where('entity_id', $venue->id)
                    ->where('status', ContractStatus::Active->value)
                    ->with('user:id,login')
                    ->get();
            }

            return $contracts
                ->map(fn (Contract $contract) => $contract->user
                    ? [
                        'id' => $contract->user->id,
                        'login' => $contract->user->login,
                        'role' => $contract->contract_type?->label(),
                    ]
                    : null
                )
                ->filter(fn ($contact) => $contact && $contact['id'] !== $user->id)
                ->unique('id')
                ->values()
                ->all();
        }

        $creator = $booking->creator;
        if (!$creator || $creator->id === $user->id) {
            return [];
        }

        return [[
            'id' => $creator->id,
            'login' => $creator->login,
            'role' => 'Заказчик',
        ]];
    }
}
