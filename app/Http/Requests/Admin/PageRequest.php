<?php

namespace App\Http\Requests\Admin;

use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $req = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'title' => [$req, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'template' => ['sometimes', Rule::in(Page::TEMPLATES)],
            'content' => ['nullable', 'string', 'max:2000000'],
            'sections' => ['nullable', 'array'],
            'status' => [$req, Rule::in(Page::STATUSES)],
            'published_at' => ['nullable', 'date'],
            'menu_order' => ['sometimes', 'integer', 'min:0'],
            'featured_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'og_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'noindex' => ['sometimes', 'boolean'],
        ];
    }
}
