<?php

namespace App\Casts;

use App\Support\Media\ImageContentRewriter;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * For columns holding HTML / rich text / JSON / serialized data with embedded
 * images (post content, page builder JSON, gallery arrays).
 *
 *   protected $casts = ['content' => \App\Casts\ImageHtml::class];
 *
 * Reading:  every img src/srcset, background url(), linked upload file and
 *           Gutenberg block URL is turned into a working absolute URL.
 * Writing:  stored with root-relative "/uploads/..." paths.
 *
 * Once `php artisan media:audit --write` has normalised the stored data, the
 * read side is a cheap no-op safety net for content edited later.
 */
class ImageHtml implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null
            ? null
            : app(ImageContentRewriter::class)->rewrite((string) $value, ImageContentRewriter::MODE_URL);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null
            ? null
            : app(ImageContentRewriter::class)->rewrite((string) $value, ImageContentRewriter::MODE_PATH);
    }
}
