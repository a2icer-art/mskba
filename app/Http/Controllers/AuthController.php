<?php

namespace App\Http\Controllers;

use App\Domain\Users\Enums\UserRegisteredVia;
use App\Domain\Users\Services\RegisterUserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Request $request)
    {
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
    }

    public function register(Request $request)
    {
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
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
