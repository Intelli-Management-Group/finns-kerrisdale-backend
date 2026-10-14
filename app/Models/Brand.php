<?php

namespace App\Models;

use App\Casts\ImageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'logo_media_id', 'logo_url', 'brand_thumbnail_media_id', 'brand_thumbnail_url', 'website_url', 'label', 'description', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'logo_url' => ImageUrl::class,
            'brand_thumbnail_url' => ImageUrl::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_media_id');
    }

    public function brandThumbnailMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'brand_thumbnail_media_id');
    }

    public function logoSrc(): ?string
    {
        return $this->logo?->url ?? $this->logo_url;
    }

    public function brandThumbnailSrc(): ?string
    {
        return $this->brandThumbnailMedia?->url ?? $this->brand_thumbnail_url;
    }
}
