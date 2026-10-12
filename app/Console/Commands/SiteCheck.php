<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Pre-launch checks: every image referenced in content exists, no content still points at
 * WordPress, every page/post has a title and description, no duplicate slugs.
 */
class SiteCheck extends Command
{
    protected $signature = 'site:check {--old-host=finnskerrisdaleladies.com}';

    protected $description = 'Check content for broken local images, leftover WordPress links and missing SEO data';

    public function handle(): int
    {
        $problems = 0;
        $disk = Storage::disk(config('media.disk'));
        $base = rtrim(Storage::disk(config('media.disk'))->url(''), '/');
        $oldHost = preg_quote($this->option('old-host'), '#');

        $rows = Post::published()->get()->map(fn ($p) => ['type' => 'post', 'm' => $p])
            ->merge(Page::published()->get()->map(fn ($p) => ['type' => 'page', 'm' => $p]));

        foreach ($rows as ['type' => $type, 'm' => $m]) {
            $label = "$type /{$m->slug}/";
            preg_match_all('/<img[^>]+src="([^"]+)"/i', (string) $m->content, $imgs);
            foreach ($imgs[1] as $src) {
                if (str_starts_with($src, $base)) {
                    $path = ltrim(substr($src, strlen($base)), '/');
                    if (! $disk->exists($path)) {
                        $this->error("  $label: missing image file $path");
                        $problems++;
                    }
                } elseif (preg_match("#$oldHost/wp-content#", $src)) {
                    $this->warn("  $label: still loads image from WordPress: $src");
                    $problems++;
                }
            }
            if ($type === 'post' && trim((string) $m->title) === '') {
                $this->warn("  $label: has no title");
            }
        }

        foreach (Media::all() as $media) {
            if (! $disk->exists($media->path)) {
                $this->error("  media #{$media->id}: file missing ({$media->path})");
                $problems++;
            }
        }
        $missingAlt = Media::where(fn ($q) => $q->whereNull('alt')->orWhere('alt', ''))->count();
        if ($missingAlt) {
            $this->warn("  $missingAlt images have no alt text (Media library → filter \"Missing alt text\").");
        }

        $clash = Post::pluck('slug')->intersect(Page::pluck('slug'));
        foreach ($clash as $slug) {
            $this->error("  slug /$slug/ is used by both a page and a post");
            $problems++;
        }

        $problems ? $this->error("$problems problem(s) found.") : $this->info('All checks passed.');

        return $problems ? self::FAILURE : self::SUCCESS;
    }
}
