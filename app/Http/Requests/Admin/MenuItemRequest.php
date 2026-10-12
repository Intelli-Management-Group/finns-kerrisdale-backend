<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'max:30'],
            'items.*.label' => ['required', 'string', 'max:60'],
            'items.*.url' => ['required', 'string', 'max:255'],
            'items.*.open_in_new_tab' => ['sometimes', 'boolean'],
            'items.*.is_active' => ['sometimes', 'boolean'],
            'location' => ['sometimes', 'in:header,footer'],
        ];
    }
}
