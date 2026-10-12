<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Full post (public detail + admin edit form).
 *
 * @mixin \App\Models\Post
 */
class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $admin = $request->is('api/admin/*');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'path' => $this->path(),
            'excerpt' => $this->excerpt,
            'summary' => $this->summary(),
            'content' => $this->content,
            'status' => $this->when($admin, $this->status),
            'published_at' => $this->published_at?->toIso8601String(),
            'is_featured' => $this->is_featured,
            'cover_url' => $this->coverUrl(),
            'featured_media_id' => $this->featured_media_id,
            'featured_media' => new MediaResource($this->whenLoaded('featuredMedia')),
            'images' => MediaResource::collection($this->whenLoaded('images')),
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'author_id' => $this->when($admin, $this->author_id),
            'author' => $this->author_name ?: $this->whenLoaded('author', fn () => $this->author?->name),
            'seo' => [
                'title' => $this->seo_title,
                'description' => $this->seo_description,
                'og_media_id' => $this->og_media_id,
                'og_image' => $this->whenLoaded('ogMedia', fn () => $this->ogMedia?->url),
                'canonical_url' => $this->canonical_url,
                'noindex' => $this->noindex,
            ],
            'created_at' => $this->when($admin, fn () => $this->created_at?->toIso8601String()),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->when($admin, fn () => $this->deleted_at?->toIso8601String()),
        ];
    }
}
