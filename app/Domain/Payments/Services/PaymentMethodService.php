<?php

namespace App\Domain\Payments\Services;

use App\Domain\Payments\Enums\PaymentMethodType;
use App\Domain\Payments\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentMethodService
{
    public function validate(Request $request): array
    {
        $data = $request->validate(
            [
                'type' => ['required', 'string', 'in:sbp,balance,acquiring'],
                'label' => ['required', 'string', 'max:120'],
                'phone' => ['nullable', 'string', 'max:32'],
                'display_name' => ['nullable', 'string', 'max:120'],
                'is_active' => ['required', 'boolean'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ],
            [
                'type.required' => 'Укажите тип метода оплаты.',
                'type.in' => 'Укажите корректный тип метода оплаты.',
                'label.required' => 'Укажите название метода оплаты.',
                'label.max' => 'Название не должно превышать 120 символов.',
                'phone.max' => 'Телефон не должен превышать 32 символа.',
                'display_name.max' => 'Имя не должно превышать 120 символов.',
            ]
        );

        if ($data['type'] === PaymentMethodType::Sbp->value) {
            if (empty($data['phone']) || empty($data['display_name'])) {
                throw ValidationException::withMessages([
                    'phone' => 'Для СБП укажите телефон и отображаемое имя.',
                ]);
            }
        }

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    public function format(PaymentMethod $method): array
    {
        return [
            'id' => $method->id,
            'type' => $method->type?->value,
            'label' => $method->label,
            'phone' => $method->phone,
            'display_name' => $method->display_name,
            'is_active' => (bool) $method->is_active,
            'sort_order' => (int) $method->sort_order,
        ];
    }

    public function ensureOwner(PaymentMethod $method, string $ownerType, int $ownerId): void
    {
        if ($method->owner_type !== $ownerType || (int) $method->owner_id !== $ownerId) {
            abort(404);
        }
    }
}
