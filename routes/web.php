<?php

use App\Domain\Users\Enums\ContactType;
use App\Domain\Users\Enums\UserRegisteredVia;
use App\Domain\Users\Models\UserContact;
use App\Domain\Users\Services\RegisterUserService;
use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueType;
use App\Domain\Participants\Enums\ParticipantRoleAssignmentStatus;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

Route::get('/', function () {
    return Inertia::render('Home', [
        'appName' => config('app.name'),
    ]);
});

Route::get('/halls', function () {
    $navItems = VenueType::query()
        ->orderBy('name')
        ->get(['name', 'alias'])
        ->map(fn (VenueType $type) => [
            'label' => $type->name,
            'href' => '/halls?type=' . $type->alias,
        ])
        ->values();

    $halls = Venue::query()
        ->with(['venueType:id,name,alias'])
        ->orderBy('name')
        ->get(['id', 'name', 'alias', 'venue_type_id', 'address', 'created_at'])
        ->map(fn (Venue $venue) => [
            'id' => $venue->id,
            'name' => $venue->name,
            'alias' => $venue->alias,
            'address' => $venue->address,
            'created_at' => $venue->created_at?->toISOString(),
            'type' => $venue->venueType?->only(['id', 'name', 'alias']),
        ])
        ->values();

    return Inertia::render('Halls', [
        'appName' => config('app.name'),
        'halls' => $halls,
        'navigation' => [
            'title' => 'Навигация',
            'items' => $navItems,
        ],
    ]);
})->name('halls');

Route::get('/login', function () {
    return redirect('/')->withErrors([
        'login' => 'Сначала войдите в аккаунт.',
    ]);
})->name('login');

Route::get('/account', function (Request $request) {
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

    return Inertia::render('Account', [
        'appName' => config('app.name'),
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
    ]);
})->name('account')->middleware('auth');

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

Route::post('/account/contacts/{contact}/confirm', function (Request $request, UserContact $contact) {
    $user = $request->user();

    if ($contact->user_id !== $user->id) {
        abort(404);
    }

    if ($contact->confirmed_at !== null) {
        return back();
    }

    $contact->update([
        'confirmed_at' => now(),
        'updated_by' => $user->id,
    ]);

    return back();
})->name('account.contacts.confirm')->middleware('auth');

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

Route::post('/account/emails/{contact}/confirm', function (Request $request, UserContact $contact) {
    $user = $request->user();

    if ($contact->user_id !== $user->id || $contact->type !== ContactType::Email) {
        abort(404);
    }

    if ($contact->confirmed_at !== null) {
        return back();
    }

    $contact->update([
        'confirmed_at' => now(),
        'updated_by' => $user->id,
    ]);

    return back();
})->name('account.emails.confirm')->middleware('auth');

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
