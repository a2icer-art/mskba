<?php

namespace App\Http\Controllers;

use App\Domain\Users\Enums\ContactType;
use App\Domain\Users\Models\UserContact;
use App\Domain\Users\Services\ContactVerificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountContactsController extends Controller
{
    public function store(Request $request)
    {
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
    }

    public function update(Request $request, UserContact $contact)
    {
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
    }

    public function destroy(Request $request, UserContact $contact)
    {
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
    }

    public function updateEmail(Request $request, UserContact $contact)
    {
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
    }

    public function destroyEmail(Request $request, UserContact $contact)
    {
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
    }

    public function requestConfirm(Request $request, UserContact $contact)
    {
        $user = $request->user();

        if ($contact->user_id !== $user->id) {
            abort(404);
        }

        if ($contact->confirmed_at !== null) {
            return back();
        }

        app(ContactVerificationService::class)->requestCode($user, $contact);

        return back();
    }

    public function verifyConfirm(Request $request, UserContact $contact)
    {
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
    }
}
