<?php

namespace App\Http\Middleware;

use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps old WordPress URLs alive after the switch:
 *  - admin-managed redirects (301/302/410)
 *  - ?p=123 and ?page_id=123 links
 *  - /wp-content/uploads/... image links (mapped to the new media URL)
 *  - WordPress system paths answer 410 Gone
 */
class HandleLegacyUrls
{
    private const GONE = ['wp-admin', 'wp-login.php', 'xmlrpc.php', 'wp-json', 'wp-includes', 'wp-cron.php'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $path = Redirect::normalizePath($request->path());
        $first = explode('/', trim($path, '/'))[0] ?? '';

        if (in_array($first, self::GONE, true)) {
            return response('Gone', 410);
        }

        // ?p=ID / ?page_id=ID (old shortlinks and guid links inside content)
        if ($path === '/' && ($id = $request->query('p') ?? $request->query('page_id'))) {
            $target = Post::published()->where('wp_id', (int) $id)->first() ?? Page::published()->where('wp_id', (int) $id)->first();
            if ($target) {
                return $this->to($request, $target->path(), 301);
            }
        }

        if ($first === 'wp-content' && str_contains($path, '/uploads/')) {
            $media = $this->findMedia($request->path());
            if ($media) {
                return $this->to($request, $media->url, 301);
            }

            return response('Not found', 404);
        }

        $map = Cache::rememberForever('redirects.map', fn () => Redirect::where('is_active', true)->get(['id', 'from_path', 'to_path', 'status_code'])->keyBy('from_path')->toArray());
        if (isset($map[$path])) {
            $r = $map[$path];
            Redirect::whereKey($r['id'])->update(['hits' => \DB::raw('hits + 1'), 'last_hit_at' => now()]);
            if ((int) $r['status_code'] === 410) {
                return response('Gone', 410);
            }

            return $this->to($request, $r['to_path'], (int) $r['status_code']);
        }

        return $next($request);
    }

    /** Laravel's redirect() strips trailing slashes; WordPress URLs keep them, so build the response directly. */
    private function to(Request $request, string $target, int $status): Response
    {
        if (! preg_match('#^https?://#i', $target)) {
            $target = $request->getSchemeAndHttpHost().'/'.ltrim($target, '/');
        }

        return new \Illuminate\Http\RedirectResponse($target, $status);
    }

    private function findMedia(string $path): ?Media
    {
        // /wp-content/uploads/2024/03/photo-300x300.jpg  ->  2024/03/photo.jpg (also match the size variant itself)
        $rel = preg_replace('#^.*?/uploads/#', '', '/'.ltrim($path, '/'));
        $original = preg_replace('/-\d+x\d+(\.\w+)$/', '$1', $rel);

        return Media::where('wp_url', 'like', '%/uploads/'.$rel)->first()
            ?? Media::where('wp_url', 'like', '%/uploads/'.$original)->first();
    }
}
