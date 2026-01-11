<?php

namespace App\Http\Requests\Venues;

use Illuminate\Foundation\Http\FormRequest;

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
}
