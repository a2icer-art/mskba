<?php

namespace App\Http\Requests\Venues;

use App\Domain\Addresses\Models\Address;
use App\Domain\Venues\Enums\VenueStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVenueRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'venue_type_id' => ['required', 'integer', 'exists:venue_types,id'],
            'city' => ['required', 'string', 'max:255'],
            'metro_id' => ['nullable', 'integer', 'exists:metros,id'],
            'street' => ['required', 'string', 'max:255'],
            'building' => ['required', 'string', 'max:255'],
            'str_address' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'city.required' => 'Необходимо указать адрес.',
            'street.required' => 'Необходимо указать адрес.',
            'building.required' => 'Не указан дом.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $city = trim((string) $this->input('city'));
            $street = trim((string) $this->input('street'));
            $building = trim((string) $this->input('building'));

            if ($city === '' || $street === '' || $building === '') {
                return;
            }

            $address = Address::query()
                ->where('city', $city)
                ->where('street', $street)
                ->where('building', $building)
                ->with('venue:id,status')
                ->first();

            if (!$address) {
                return;
            }

            $message = 'Площадка с таким адресом уже существует.';
            $status = $this->normalizeStatus($address->venue?->status);
            if ($status && $status !== VenueStatus::Confirmed) {
                $message .= ' ' . $this->formatStatusSuffix($status) . '.';
            }

            $validator->errors()->add('city', $message);
        });
    }

    private function normalizeStatus(mixed $status): ?VenueStatus
    {
        if ($status instanceof VenueStatus) {
            return $status;
        }

        if (is_string($status)) {
            return VenueStatus::tryFrom($status);
        }

        return null;
    }

    private function formatStatusSuffix(VenueStatus $status): string
    {
        return match ($status) {
            VenueStatus::Moderation => 'на модерации',
            VenueStatus::Unconfirmed => 'не подтверждена',
            VenueStatus::Blocked => 'заблокирована',
            default => 'не подтверждена',
        };
    }
}