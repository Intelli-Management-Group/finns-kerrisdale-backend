<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Slug
{
    /** Slugs that would clash with fixed public routes. */
    public const RESERVED = ['api', 'admin', 'blog', 'category', 'tag', 'feed', 'sitemap.xml', 'robots.txt', 'storage', 'uploads', 'wp-admin', 'wp-content', 'wp-login.php', 'page', 'gallery'];

    /**
     * @param  class-string<Model>  $model
     */
    public static function unique(string $model, string $source, ?int $ignoreId = null, array $reserved = [], array $alsoCheck = []): string
    {
        $base = Str::slug($source) ?: 'item';
        $slug = $base;
        $i = 2;
        while (in_array($slug, $reserved, true) || self::exists($model, $slug, $ignoreId) || self::existsInOthers($alsoCheck, $slug)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * Pages and posts share the root URL space (/{slug}/), so a slug must be free in both tables.
     */
    public static function forContent(string $model, string $source, ?int $ignoreId = null): string
    {
        $other = $model === \App\Models\Post::class ? \App\Models\Page::class : \App\Models\Post::class;

        return self::unique($model, $source, $ignoreId, self::RESERVED, [$other]);
    }

    private static function existsInOthers(array $models, string $slug): bool
    {
        foreach ($models as $m) {
            if (self::exists($m, $slug, null)) {
                return true;
            }
        }

        return false;
    }

    private static function exists(string $model, string $slug, ?int $ignoreId): bool
    {
        $q = $model::query()->where('slug', $slug);
        if (method_exists($model, 'bootSoftDeletes')) {
            $q->withTrashed();
        }
        if ($ignoreId) {
            $q->whereKeyNot($ignoreId);
        }

        return $q->exists();
    }
}
