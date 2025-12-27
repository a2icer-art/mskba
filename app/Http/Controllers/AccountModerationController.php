<?php

namespace App\Http\Controllers;

use App\Domain\Moderation\Enums\ModerationEntityType;
use App\Domain\Moderation\UseCases\SubmitModerationRequest;
use Illuminate\Http\Request;

class AccountModerationController extends Controller
{
    public function store(Request $request, SubmitModerationRequest $useCase)
    {
        $user = $request->user();

        $result = $useCase->execute($user, ModerationEntityType::User, $user);

        if (!$result->success) {
            return back()->withErrors([
                'moderation' => implode("\n", $result->errors),
            ]);
        }

        return back();
    }
}
