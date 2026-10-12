<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Media */
class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'variants' => $this->variantUrls(),
            'folder' => $this->folder,
            'filename' => $this->filename,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'width' => $this->width,
            'height' => $this->height,
            'size_bytes' => $this->size_bytes,
            'original_size_bytes' => $this->original_size_bytes,
            'alt' => $this->alt,
            'title' => $this->title,
            'caption' => $this->caption,
            'created_at' => $this->created_at?->toIso8601String(),
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $this->uploader?->name),
        ];
    }
}
