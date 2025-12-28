<?php

namespace App\Http\Controllers;

use App\Domain\Users\Services\AccountProfileUpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rules\Password;

class AccountProfileController extends Controller
{
    public function update(Request $request, AccountProfileUpdateService $service)
    {
        $user = $request->user();

        $fields = $service->getEditableFields($user);
        $rules = Arr::only($service->getProfileValidationRules($user), $fields);
        $validated = validator($request->only($fields), $rules)->validate();

        $service->updateProfile($user, $validated);

        return back();
    }

    public function updatePassword(Request $request, AccountProfileUpdateService $service)
    {
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['required', Password::min(6)->letters()->numbers()],
        ]);

        $service->updatePassword($user, $validated['password']);

        return back();
    }
}
