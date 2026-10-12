<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RedirectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $req = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'from_path' => [$req, 'string', 'max:255', 'starts_with:/'],
            'to_path' => ['nullable', 'required_unless:status_code,410', 'string', 'max:255'],
            'status_code' => [$req, Rule::in([301, 302, 410])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
