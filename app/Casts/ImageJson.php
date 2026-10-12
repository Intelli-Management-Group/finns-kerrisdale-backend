<?php

namespace App\Casts;

use App\Support\Media\ImageContentRewriter;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class ImageJson implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null) {
            return null;
        }

        $json = app(ImageContentRewriter::class)->rewrite((string) $value, ImageContentRewriter::MODE_URL);

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        $json = is_string($value)
            ? $value
            : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return app(ImageContentRewriter::class)->rewrite($json, ImageContentRewriter::MODE_PATH);
    }
}
