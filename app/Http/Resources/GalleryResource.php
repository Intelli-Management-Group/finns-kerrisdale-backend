<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Gallery */
class GalleryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->when($request->is('api/admin/*'), $this->status),
            'sort_order' => $this->sort_order,
            'cover_media_id' => $this->cover_media_id,
            'cover_url' => $this->cover?->url ?? $this->whenLoaded('media', fn () => $this->media->first()?->url),
            'media_count' => $this->whenCounted('media'),
            'photos' => $this->whenLoaded('media', fn () => $this->media->map(fn ($m) => array_merge(
                (new MediaResource($m))->resolve($request),
                ['caption' => $m->pivot->caption ?? $m->caption, 'sort_order' => $m->pivot->sort_order]
            ))),
        ];
    }
}
