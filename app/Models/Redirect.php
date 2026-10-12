<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Redirect extends Model
{
    protected $fillable = ['from_path', 'to_path', 'status_code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_hit_at' => 'datetime', 'status_code' => 'integer', 'hits' => 'integer'];
    }

    /** Normalise to "/path/" form (leading + trailing slash, no host, lower-case). */
    public static function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?? '/';
        $path = '/'.trim(rawurldecode($path), '/');

        return $path === '/' ? '/' : strtolower($path).'/';
    }

    /**
     * A live URL changed (slug edit): 301 the old address to the new one, re-point older redirects
     * at the new address (no chains) and make sure the new address itself is not redirected away.
     */
    public static function rememberMove(string $oldPath, string $newPath): void
    {
        $old = self::normalizePath($oldPath);
        $new = self::normalizePath($newPath);
        if ($old === $new || $old === '/') {
            return;
        }
        self::query()->where('from_path', $new)->delete();
        self::query()->where('to_path', $old)->update(['to_path' => $new]);
        self::query()->updateOrCreate(['from_path' => $old], ['to_path' => $new, 'status_code' => 301, 'is_active' => true]);
        Cache::forget('redirects.map');
    }
}
