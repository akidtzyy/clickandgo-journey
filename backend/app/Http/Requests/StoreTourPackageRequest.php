<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTourPackageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'duration'    => ['required', 'string', 'max:100'],
            'price'       => ['required', 'numeric', 'min:0'],
            'highlights'  => ['required', 'array', 'min:1'],
            'highlights.*'=> ['required', 'string'],
            'included'    => ['required', 'array', 'min:1'],
            'included.*'  => ['required', 'string'],
            'category'    => ['required', 'string', 'max:100'],
            'image_url'   => ['nullable', 'url'],
            'is_available'=> ['boolean'],
        ];
    }
}
