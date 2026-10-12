<?php

namespace App\Http\Requests\Admin;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $creating = $this->isMethod('post');
        $req = $creating ? 'required' : 'sometimes';

        return [
            'title' => [$req, 'nullable', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:200', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['nullable', 'string', 'max:2000000'],
            'status' => [$req, Rule::in(Post::STATUSES)],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['sometimes', 'boolean'],
            'featured_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'og_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'author_id' => ['nullable', 'integer', 'exists:users,id'],
            'author_name' => ['nullable', 'string', 'max:120'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:categories,id'],
            'tags' => ['sometimes', 'array', 'max:30'],
            'tags.*' => ['string', 'max:60'],
            'image_ids' => ['sometimes', 'array', 'max:100'],
            'image_ids.*' => ['integer', 'exists:media,id'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url', 'max:255'],
            'noindex' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['slug.regex' => 'The slug may only contain lower-case letters, numbers and single hyphens.'];
    }
}
