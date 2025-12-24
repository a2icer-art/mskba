<?php

use App\Domain\Places\Models\Place;
use App\Domain\Places\Models\PlaceType;
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
    $navItems = PlaceType::query()
        ->orderBy('name')
        ->get(['name', 'alias'])
        ->map(fn (PlaceType $type) => [
            'label' => $type->name,
            'href' => '/halls?type=' . $type->alias,
        ])
        ->values();

    $halls = Place::query()
        ->with(['placeType:id,name,alias'])
        ->orderBy('name')
        ->get(['id', 'name', 'alias', 'place_type_id', 'address', 'created_at'])
        ->map(fn (Place $place) => [
            'id' => $place->id,
            'name' => $place->name,
            'alias' => $place->alias,
            'address' => $place->address,
            'created_at' => $place->created_at?->toISOString(),
            'type' => $place->placeType?->only(['id', 'name', 'alias']),
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
