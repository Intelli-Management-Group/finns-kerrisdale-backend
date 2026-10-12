<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight post row for lists and tables. Pass ?with_content=1 on the public list to get full HTML
 * (the WordPress blog page shows each post's full content, not an excerpt).
 *
 * @mixin \App\Models\Post
 */
class PostListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $admin = $request->is('api/admin/*');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'path' => $this->path(),
            'summary' => $this->summary(),
            'content' => $this->when($request->boolean('with_content'), $this->content),
            'published_at' => $this->published_at?->toIso8601String(),
            'cover_url' => $this->coverUrl(),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'author' => $this->author_name ?: $this->whenLoaded('author', fn () => $this->author?->name),
            'status' => $this->when($admin, $this->status),
            'updated_at' => $this->when($admin, fn () => $this->updated_at?->toIso8601String()),
            'deleted_at' => $this->when($admin, fn () => $this->deleted_at?->toIso8601String()),
        ];
    }
}
