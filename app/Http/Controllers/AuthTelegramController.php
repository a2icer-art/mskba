<?php

namespace App\Http\Controllers;

use App\Domain\Users\Services\TelegramLoginService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthTelegramController extends Controller
{
    public function createToken(Request $request, TelegramLoginService $service)
    {
        $payload = $service->createLoginToken($request);

        return response()->json($payload);
    }

    public function complete(Request $request, TelegramLoginService $service)
    {
        $token = (string) $request->query('token', '');
        if ($token === '') {
            return redirect('/')
                ->withErrors(['telegram' => 'Ссылка недействительна. Попробуйте начать вход заново.']);
        }

        $result = $service->completeLogin($token, $request);
        if (!$result['success']) {
            return redirect('/')
                ->withErrors(['telegram' => $result['message']]);
        }

        $user = $result['user'];
        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/')
            ->with('notice', 'Вы вошли через Telegram.');
    }
}
