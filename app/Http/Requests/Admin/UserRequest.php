<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $id = $this->route('user')?->id;

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'email' => [$creating ? 'required' : 'sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'password' => [$creating ? 'required' : 'nullable', 'string', Password::min(10)->letters()->numbers()],
            'role' => [$creating ? 'required' : 'sometimes', Rule::in(User::ROLES)],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
