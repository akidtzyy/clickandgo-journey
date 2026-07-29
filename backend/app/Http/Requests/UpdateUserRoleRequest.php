<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role'    => ['required', 'in:user,admin'],
        ];
    }

    public function messages(): array
    {
        return [
            'role.in' => 'Role yang dapat ditetapkan hanya "user" atau "admin". Super Admin tidak dapat di-assign via API.',
        ];
    }
}
