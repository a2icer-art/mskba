<?php

use App\Domain\Users\Enums\UserRegisteredVia;
use App\Domain\Users\Services\RegisterUserService;
use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueType;
use App\Domain\Participants\Enums\ParticipantRoleAssignmentStatus;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Validation\Rules\Password;

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

    $user->load('profile');

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
    ]);
})->name('account')->middleware('auth');

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
        'email' => ['nullable', 'email', 'max:255', 'unique:user_emails,email'],
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
