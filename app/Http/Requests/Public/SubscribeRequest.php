<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

class SubscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email:rfc', 'max:255'],
            'website' => ['nullable', 'max:0'], // honeypot: real people never fill this
            'source' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return ['website.max' => 'Spam detected.'];
    }
}
