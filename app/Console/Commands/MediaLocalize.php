<?php

namespace App\Console\Commands;

use App\Models\Brand;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Services\MediaService;
use App\Services\SettingsService;
use App\Support\ContentCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Downloads images that content still loads from the old WordPress site, runs them through the
 * compression pipeline, and rewrites the content to use the new local URLs.
 */
class MediaLocalize extends Command
{
    protected $signature = 'media:localize {--host=finnskerrisdaleladies.com : Host to pull images from} {--dry-run}';

    protected $description = 'Copy remote WordPress images into the media library (compressed, WebP) and update content links';

    public function handle(MediaService $media, SettingsService $settings): int
    {
        $host = preg_quote($this->option('host'), '#');
        $pattern = '#https?://(?:www\.)?'.$host.'/wp-content/(?:uploads|themes)/[^"\'\s)<>?]+\.(?:jpe?g|png|gif|webp)#i';
        $urls = [];

        $sources = [];
        foreach (Post::withTrashed()->get() as $p) {
            $sources[] = $p;
        }
        foreach (Page::withTrashed()->get() as $p) {
            $sources[] = $p;
        }
        foreach ($sources as $row) {
            preg_match_all($pattern, (string) $row->content, $m);
            foreach ($m[0] as $u) {
                $urls[$u] = true;
            }
        }
        foreach (Brand::whereNotNull('logo_url')->pluck('logo_url') as $u) {
            if (preg_match($pattern, $u)) {
                $urls[$u] = true;
            }
        }
        foreach (['logo_url', 'og_default_image'] as $key) {
            $u = (string) $settings->get($key);
            if (preg_match($pattern, $u)) {
                $urls[$u] = true;
            }
        }

        $this->info(count($urls).' remote image URLs found.');
        if ($this->option('dry-run') || ! $urls) {
            return self::SUCCESS;
        }

        $map = [];
        $bar = $this->output->createProgressBar(count($urls));
        foreach (array_keys($urls) as $url) {
            $bar->advance();
            $original = preg_replace('/-\d+x\d+(\.\w+)$/', '$1', $url);
            $existing = Media::where('wp_url', $original)->orWhere('wp_url', $url)->first();
            if (! $existing) {
                $tmp = null;
                try {
                    // Prefer the full-size original; fall back to the exact file referenced.
                    foreach (array_unique([$original, $url]) as $try) {
                        $res = Http::timeout(60)->retry(2, 500)->get($try);
                        if ($res->successful() && str_starts_with((string) $res->header('Content-Type'), 'image/')) {
                            $tmp = tempnam(sys_get_temp_dir(), 'loc');
                            file_put_contents($tmp, $res->body());
                            preg_match('#/uploads/(\d{4})/(\d{2})/#', $try, $d);
                            $existing = $media->storeFromPath($tmp, basename(parse_url($try, PHP_URL_PATH)),
                                str_contains($try, '/themes/') ? 'brands' : (str_contains($try, '/2015/02/') ? 'pages' : 'posts'),
                                null, ['wp_url' => $try], $d ? "{$d[1]}-{$d[2]}-01" : null);
                            break;
                        }
                    }
                } catch (Throwable $e) {
                    $this->newLine();
                    $this->warn("  failed: $url ({$e->getMessage()})");
                } finally {
                    if ($tmp) {
                        @unlink($tmp);
                    }
                }
            }
            if ($existing) {
                $map[$url] = $this->sizedUrl($existing, $url);
            }
        }
        $bar->finish();
        $this->newLine();

        foreach ($sources as $row) {
            $new = strtr((string) $row->content, $map);
            $new = preg_replace('/\s(srcset|sizes)="[^"]*"/i', '', $new);
            if ($new !== $row->content) {
                $row->content = $new;
                $row->saveQuietly();
            }
        }
        foreach (Brand::all() as $b) {
            if (isset($map[$b->logo_url])) {
                $b->update(['logo_media_id' => Media::where('wp_url', $b->logo_url)->value('id'), 'logo_url' => null]);
            }
        }
        foreach (['logo_url', 'og_default_image'] as $key) {
            $u = (string) $settings->get($key);
            if (isset($map[$u])) {
                $settings->update([$key => Media::where('wp_url', $u)->first()?->url]);
            }
        }
        ContentCache::bust();
        $this->info(count($map).' images now served locally.');

        return self::SUCCESS;
    }

    private function sizedUrl(Media $media, string $url): string
    {
        if (preg_match('/-(\d+)x(\d+)\.\w+$/', $url, $m)) {
            $size = max((int) $m[1], (int) $m[2]);
            $variants = $media->variantUrls();
            foreach (config('media.variants') as $name => $px) {
                if ($size <= $px && isset($variants[$name])) {
                    return $variants[$name];
                }
            }
        }

        return $media->url;
    }
}
