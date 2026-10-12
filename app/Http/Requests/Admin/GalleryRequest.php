<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $req = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title' => [$req, 'string', 'max:200'],
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cover_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'status' => [$req, Rule::in(['draft', 'published'])],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'photos' => ['sometimes', 'array', 'max:500'],
            'photos.*.media_id' => ['required', 'integer', 'exists:media,id'],
            'photos.*.caption' => ['nullable', 'string', 'max:255'],
        ];
    }
}
