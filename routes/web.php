<?php

use App\Domain\Users\Enums\ContactType;
use App\Domain\Users\Enums\UserRegisteredVia;
use App\Domain\Participants\Models\ParticipantRoleAssignment;
use App\Domain\Users\Models\ContactVerification;
use App\Domain\Users\Models\UserContact;
use App\Domain\Users\Services\ContactVerificationService;
use App\Domain\Users\Services\RegisterUserService;
use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueType;
use App\Domain\Venues\Services\VenueCatalogService;
use App\Domain\Participants\Enums\ParticipantRoleAssignmentStatus;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

Route::get('/', function () {
    return Inertia::render('Home', [
        'appName' => config('app.name'),
    ]);
});

Route::get('/venues', function () {
    $catalog = app(VenueCatalogService::class);
    $navItems = $catalog->getNavigationItems();
    $catalogData = $catalog->getHallsList();

    return Inertia::render('Halls', [
        'appName' => config('app.name'),
        'halls' => $catalogData['halls'],
        'activeType' => $catalogData['activeType'],
        'activeTypeSlug' => $catalogData['activeTypeSlug'],
        'navigation' => [
            'title' => 'Площадки',
            'items' => $navItems,
        ],
    ]);
})->name('venues');

Route::get('/venues/{type}', function (string $type) {
    $catalog = app(VenueCatalogService::class);
    $navItems = $catalog->getNavigationItems();
    $catalogData = $catalog->getHallsList($type);

    if (!$catalogData) {
        abort(404);
    }

    return Inertia::render('Halls', [
        'appName' => config('app.name'),
        'halls' => $catalogData['halls'],
        'activeType' => $catalogData['activeType'],
        'activeTypeSlug' => $catalogData['activeTypeSlug'],
        'navigation' => [
            'title' => 'Навигация',
            'items' => $navItems,
        ],
    ]);
})->name('venues.type');

Route::get('/login', function () {
    return redirect('/')->withErrors([
        'login' => 'Сначала войдите в аккаунт.',
    ]);
})->name('login');

$renderAccount = function (Request $request, string $activeTab = 'user') {
        $user = $request->user();

        $user->load(['profile', 'contacts']);
        $contactTypes = [
            ['value' => ContactType::Email->value, 'label' => 'Электронная почта'],
            ['value' => ContactType::Phone->value, 'label' => 'Телефон'],
            ['value' => ContactType::Telegram->value, 'label' => 'Telegram'],
            ['value' => ContactType::Vk->value, 'label' => 'VK'],
            ['value' => ContactType::Other->value, 'label' => 'Другое'],
        ];

        $participantRoles = $user->participantRoleAssignments()
            ->with('role:id,name,alias')
            ->where('status', ParticipantRoleAssignmentStatus::Confirmed)
            ->get()
            ->map(fn ($assignment) => [
                'id' => $assignment->id,
                'label' => $assignment->custom_title ?: ($assignment->role?->name ?? 'Роль'),
                'alias' => $assignment->role?->alias,
            ])
            ->values();

        $contactVerifications = [];
        $contactIds = $user->contacts->pluck('id')->all();

        if ($contactIds !== []) {
            $verifications = ContactVerification::query()
                ->whereIn('contact_id', $contactIds)
                ->whereNull('verified_at')
                ->where('expires_at', '>', now())
                ->orderByDesc('id')
                ->get(['contact_id', 'attempts', 'expires_at']);

            foreach ($verifications as $verification) {
                if (array_key_exists($verification->contact_id, $contactVerifications)) {
                    continue;
                }

                $contactVerifications[$verification->contact_id] = [
                    'attempts' => $verification->attempts,
                    'max_attempts' => ContactVerificationService::MAX_ATTEMPTS,
                    'expires_at' => $verification->expires_at?->toISOString(),
                ];
            }
        }

        return Inertia::render('Account', [
            'appName' => config('app.name'),
            'activeTab' => $activeTab,
            'user' => [
                'id' => $user->id,
                'login' => $user->login,
                'status' => $user->status?->value,
                'created_at' => $user->created_at?->toISOString(),
                'confirmed_at' => $user->confirmed_at?->toISOString(),
            ],
            'profile' => $user->profile?->only(['first_name', 'last_name', 'middle_name', 'gender', 'birth_date']),
            'participantRoles' => $participantRoles,
            'emails' => $user->contacts
                ->where('type', ContactType::Email)
                ->sortBy('id')
                ->map(fn (UserContact $contact) => [
                    'id' => $contact->id,
                    'email' => $contact->value,
                    'confirmed_at' => $contact->confirmed_at?->toISOString(),
                ])
                ->values(),
            'contacts' => $user->contacts
                ->sortBy('id')
                ->map(fn (UserContact $contact) => [
                    'id' => $contact->id,
                    'type' => $contact->type?->value,
                    'value' => $contact->value,
                    'confirmed_at' => $contact->confirmed_at?->toISOString(),
                ])
                ->values(),
            'contactTypes' => $contactTypes,
            'contactVerifications' => $contactVerifications,
        ]);
};

Route::get('/account', function (Request $request) use ($renderAccount) {
    return $renderAccount($request, 'user');
})->name('account')->middleware('auth');

Route::get('/account/profile', function (Request $request) use ($renderAccount) {
    return $renderAccount($request, 'profile');
})->name('account.profile')->middleware('auth');

Route::get('/account/contacts', function (Request $request) use ($renderAccount) {
    return $renderAccount($request, 'contacts');
})->name('account.contacts')->middleware('auth');

Route::get('/account/roles/{assignment}', function (Request $request, ParticipantRoleAssignment $assignment) use ($renderAccount) {
    $user = $request->user();

    if ($assignment->user_id !== $user->id || $assignment->status !== ParticipantRoleAssignmentStatus::Confirmed) {
        abort(404);
    }

    return $renderAccount($request, 'role-' . $assignment->id);
})->name('account.roles.show')->middleware('auth');

Route::post('/account/contacts', function (Request $request) {
    $user = $request->user();
    $typeValues = array_map(fn (ContactType $type) => $type->value, ContactType::cases());
    $type = $request->input('type');

    $rules = [
        'type' => ['required', Rule::in($typeValues)],
        'value' => [
            'required',
            'string',
            'max:255',
            Rule::unique('user_contacts', 'value')
                ->where('user_id', $user->id)
                ->where('type', $type),
        ],
    ];

    if ($type === ContactType::Email->value) {
        $rules['value'][] = 'email';
    }

    $validated = $request->validate($rules, [
        'value.unique' => 'Этот контакт уже добавлен.',
    ]);

    UserContact::query()->create([
        'user_id' => $user->id,
        'type' => $validated['type'],
        'value' => $validated['value'],
        'confirmed_at' => null,
        'created_by' => $user->id,
        'updated_by' => $user->id,
    ]);

    return back();
})->name('account.contacts.store')->middleware('auth');

Route::patch('/account/contacts/{contact}', function (Request $request, UserContact $contact) {
    $user = $request->user();

    if ($contact->user_id !== $user->id) {
        abort(404);
    }

    if ($contact->confirmed_at !== null) {
        return back()->withErrors([
            'contact' => 'Подтвержденный контакт нельзя редактировать.',
        ]);
    }

    $rules = [
        'value' => [
            'required',
            'string',
            'max:255',
            Rule::unique('user_contacts', 'value')
                ->where('user_id', $user->id)
                ->where('type', $contact->type?->value)
                ->ignore($contact->id),
        ],
    ];

    if ($contact->type === ContactType::Email) {
        $rules['value'][] = 'email';
    }

    $validated = $request->validate($rules, [
        'value.unique' => 'Этот контакт уже добавлен.',
    ]);

    $contact->update([
        'value' => $validated['value'],
        'updated_by' => $user->id,
    ]);

    return back();
})->name('account.contacts.update')->middleware('auth');

Route::post('/account/contacts/{contact}/confirm-request', function (Request $request, UserContact $contact) {
    $user = $request->user();

    if ($contact->user_id !== $user->id) {
        abort(404);
    }

    if ($contact->confirmed_at !== null) {
        return back();
    }

    app(ContactVerificationService::class)->requestCode($user, $contact);

    return back();
})->name('account.contacts.confirm.request')->middleware('auth');

Route::post('/account/contacts/{contact}/confirm-verify', function (Request $request, UserContact $contact) {
    $user = $request->user();

    if ($contact->user_id !== $user->id) {
        abort(404);
    }

    if ($contact->confirmed_at !== null) {
        return back();
    }

    $validated = $request->validate([
        'code' => ['required', 'string', 'max:20'],
    ]);

    app(ContactVerificationService::class)->verifyCode($user, $contact, $validated['code']);

    return back();
})->name('account.contacts.confirm.verify')->middleware('auth');

Route::delete('/account/contacts/{contact}', function (Request $request, UserContact $contact) {
    $user = $request->user();

    if ($contact->user_id !== $user->id) {
        abort(404);
    }

    if ($contact->confirmed_at !== null) {
        return back()->withErrors([
            'contact' => 'Подтвержденный контакт нельзя удалить.',
        ]);
    }

    $contact->forceDelete();

    return back();
})->name('account.contacts.destroy')->middleware('auth');

Route::patch('/account/emails/{contact}', function (Request $request, UserContact $contact) {
    $user = $request->user();

    if ($contact->user_id !== $user->id || $contact->type !== ContactType::Email) {
        abort(404);
    }

    if ($contact->confirmed_at !== null) {
        return back()->withErrors([
            'email' => 'Подтвержденный email нельзя редактировать.',
        ]);
    }

    $validated = $request->validate([
        'email' => [
            'required',
            'email',
            'max:255',
            Rule::unique('user_contacts', 'value')
                ->where('user_id', $user->id)
                ->where('type', ContactType::Email->value)
                ->ignore($contact->id),
        ],
    ], [
        'email.unique' => 'Этот email уже добавлен.',
    ]);

    $contact->update([
        'value' => $validated['email'],
        'updated_by' => $user->id,
    ]);

    return back();
})->name('account.emails.update')->middleware('auth');

Route::delete('/account/emails/{contact}', function (Request $request, UserContact $contact) {
    $user = $request->user();

    if ($contact->user_id !== $user->id || $contact->type !== ContactType::Email) {
        abort(404);
    }

    if ($contact->confirmed_at !== null) {
        return back()->withErrors([
            'email' => 'Подтвержденный email нельзя удалить.',
        ]);
    }

    $contact->forceDelete();

    return back();
})->name('account.emails.destroy')->middleware('auth');


Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'login' => ['required', 'string'],
        'password' => ['required'],
    ]);

    if (!Auth::attempt($credentials, $request->boolean('remember'))) {
        return back()->withErrors([
            'login' => 'Неверный логин или пароль.',
        ]);
    }

    $request->session()->regenerate();

    return back();
})->name('login.store');

Route::post('/register', function (Request $request) {
    $validated = $request->validate([
        'login' => ['required', 'string', 'max:255', 'unique:users,login'],
        'email' => [
            'nullable',
            'email',
            'max:255',
        ],
        'password' => ['required', Password::min(6)->letters()->numbers()],
        'participant_role_id' => ['nullable', 'integer', 'exists:participant_roles,id'],
    ]);

    $user = app(RegisterUserService::class)->register([
        'login' => $validated['login'],
        'email' => $validated['email'] ?? null,
        'password' => $validated['password'],
        'participant_role_id' => $validated['participant_role_id'] ?? null,
        'registered_via' => UserRegisteredVia::Site,
    ]);

    Auth::login($user);

    return redirect('/');
})->name('register.store');

Route::post('/logout', function (Request $request) {
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return back();
})->name('logout');
