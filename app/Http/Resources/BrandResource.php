<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Brand */
class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_media_id' => $this->logo_media_id,
            'logo_url' => $this->logoSrc(),
            'brand_thumbnail_media_id' => $this->brand_thumbnail_media_id,
            'brand_thumbnail_url' => $this->brandThumbnailSrc(),
            'website_url' => $this->website_url,
            'label' => $this->label,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_active' => $this->when($request->is('api/admin/*'), $this->is_active),
        ];
    }
}
