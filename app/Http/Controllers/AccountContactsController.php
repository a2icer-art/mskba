<?php

namespace App\Http\Controllers;

use App\Domain\Users\Enums\ContactType;
use App\Domain\Users\Models\UserContact;
use App\Domain\Users\Services\ContactVerificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountContactsController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();
        $typeValues = array_map(fn (ContactType $type) => $type->value, ContactType::cases());
        $type = $request->input('type');

        $value = (string) $request->input('value');
        if ($type === ContactType::Telegram->value) {
            $value = $this->normalizeTelegramValue($value);
            $request->merge(['value' => $value]);
        }

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

        $value = (string) $request->input('value');
        if ($contact->type === ContactType::Telegram) {
            $value = $this->normalizeTelegramValue($value);
            $request->merge(['value' => $value]);
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

        if ($contact->type === ContactType::Telegram) {
            $payload = app(ContactVerificationService::class)->requestTelegramLink($user, $contact);
            session()->flash('telegram_verification', [
                'contact_id' => $contact->id,
                'link' => $payload['link'],
                'expires_at' => $payload['expires_at']?->toDateTimeString(),
            ]);
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

        if ($contact->type === ContactType::Telegram) {
            throw ValidationException::withMessages([
                'code' => 'Подтверждение Telegram выполняется через бота.',
            ]);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
        ]);

        app(ContactVerificationService::class)->verifyCode($user, $contact, $validated['code']);

        return back();
    }

    private function normalizeTelegramValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        $value = ltrim($value, '@');

        if (preg_match('/^\d+$/', $value)) {
            return $value;
        }

        $value = strtolower($value);

        return '@' . $value;
    }
}
