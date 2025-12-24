<?php

use App\Domain\Venues\Models\Venue;
use App\Domain\Venues\Models\VenueType;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

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

    return Inertia::render('Account', [
        'appName' => config('app.name'),
        'user' => $user->only(['id', 'name', 'email', 'login']),
        'profile' => $user->profile?->only(['first_name', 'last_name', 'middle_name', 'gender', 'birth_date']),
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

Route::post('/logout', function (Request $request) {
    Auth::logout();

    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return back();
})->name('logout');
