<?php

namespace App\Http\Controllers\Integrations;

use App\Domain\Users\Enums\ContactType;
use App\Domain\Users\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSuggestController
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'query' => ['required', 'string', 'min:2'],
        ]);

        $query = $data['query'];

        $users = User::query()
            ->where('status', UserStatus::Confirmed->value)
            ->where(function ($builder) use ($query) {
                $builder->where('login', 'like', "%{$query}%")
                    ->orWhereHas('contacts', function ($contactQuery) use ($query) {
                        $contactQuery
                            ->where('type', ContactType::Email->value)
                            ->whereNotNull('confirmed_at')
                            ->where('value', 'like', "%{$query}%");
                    });
            })
            ->with(['contacts' => function ($contactQuery) {
                $contactQuery
                    ->where('type', ContactType::Email->value)
                    ->whereNotNull('confirmed_at');
            }])
            ->orderBy('login')
            ->limit(10)
            ->get(['id', 'login']);

        $suggestions = $users->map(function (User $user): array {
            $email = $user->contacts->first()?->value;
            $label = $email ? "{$user->login} ({$email})" : $user->login;

            return [
                'id' => $user->id,
                'login' => $user->login,
                'email' => $email,
                'label' => $label,
            ];
        })->all();

        return response()->json([
            'suggestions' => $suggestions,
        ]);
    }
}
