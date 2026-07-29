<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCarRentalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'type'         => ['required', 'string', 'max:100'],
            'capacity'     => ['required', 'integer', 'min:1', 'max:50'],
            'price'        => ['required', 'numeric', 'min:0'],
            'is_available' => ['boolean'],
            'image_url'    => ['nullable', 'url'],
        ];
    }
}
