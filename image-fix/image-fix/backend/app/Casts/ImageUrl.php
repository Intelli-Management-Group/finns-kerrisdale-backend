<?php

namespace App\Casts;

use App\Support\Media\ImageUrlResolver;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * For columns holding ONE image reference (featured_image, thumbnail, logo).
 *
 *   protected $casts = ['featured_image' => \App\Casts\ImageUrl::class];
 *
 * Reading:  any legacy/relative form -> absolute working URL (API JSON gets this).
 * Writing:  stored as root-relative "/uploads/..." so no domain is hardcoded.
 */
class ImageUrl implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : app(ImageUrlResolver::class)->toUrl((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : app(ImageUrlResolver::class)->toPath((string) $value);
    }
}
