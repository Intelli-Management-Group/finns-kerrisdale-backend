<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Page */
class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $admin = $request->is('api/admin/*');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'path' => $this->path(),
            'template' => $this->template,
            'content' => $this->when($admin || ! $request->boolean('list'), $this->content),
            'sections' => $this->sections,
            'featured_media_id' => $this->featured_media_id,
            'featured_media' => new MediaResource($this->whenLoaded('featuredMedia')),
            'status' => $this->when($admin, $this->status),
            'published_at' => $this->published_at?->toIso8601String(),
            'menu_order' => $this->menu_order,
            'seo' => [
                'title' => $this->seo_title,
                'description' => $this->seo_description,
                'og_media_id' => $this->og_media_id,
                'og_image' => $this->whenLoaded('ogMedia', fn () => $this->ogMedia?->url),
                'canonical_url' => $this->canonical_url,
                'noindex' => $this->noindex,
            ],
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->when($admin, fn () => $this->deleted_at?->toIso8601String()),
        ];
    }
}
