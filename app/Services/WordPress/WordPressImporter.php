<?php

namespace App\Services\WordPress;

use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Models\Subscriber;
use App\Models\Tag;
use App\Models\User;
use App\Services\MediaService;
use App\Support\ContentCache;
use App\Support\Html;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Imports a WordPress database (+ uploads) into the Laravel schema.
 *
 * Idempotent: every imported row keeps its WordPress id (wp_id) and is updated, not duplicated, on re-runs.
 * Rows created by the snapshot seeder are matched by slug and adopted.
 */
class WordPressImporter
{
    private Connection $wp;

    private string $p; // table prefix

    /** @var array<int,int> wp attachment id => media id */
    private array $mediaMap = [];

    /** @var array<string,Media> "2024/03/file.jpg" => media */
    private array $fileMap = [];

    /** @var array<int,int> */
    private array $userMap = [];

    /** @var array<int,int> wp term_taxonomy_id => local category id */
    private array $catMap = [];

    /** @var array<int,int> */
    private array $tagMap = [];

    private array $report = [
        'users' => 0, 'categories' => 0, 'tags' => 0, 'media' => 0, 'media_skipped' => 0, 'media_failed' => [],
        'pages_created' => 0, 'pages_updated' => 0, 'posts_created' => 0, 'posts_updated' => 0,
        'subscribers' => 0, 'comments_found' => 0, 'redirects' => 0, 'warnings' => [],
    ];

    private ?Closure $log = null;

    public function __construct(private MediaService $media) {}

    /**
     * @param array{
     *   connection?:string, prefix?:string, uploads?:?string, site_url?:string, download?:bool,
     *   dry_run?:bool, skip_media?:bool, overwrite_pages?:bool
     * } $options
     */
    public function run(array $options, ?Closure $log = null): array
    {
        $this->log = $log;
        $this->wp = DB::connection($options['connection'] ?? 'wordpress');
        $this->p = $options['prefix'] ?? 'wp_';
        $opts = array_merge([
            'uploads' => null, 'site_url' => 'https://finnskerrisdaleladies.com', 'download' => false,
            'dry_run' => false, 'skip_media' => false, 'overwrite_pages' => false,
        ], $options);

        $this->assertWordPress();

        $work = function () use ($opts) {
            $this->importUsers();
            $this->importTerms();
            if (! $opts['skip_media']) {
                $this->importMedia($opts);
            } else {
                $this->loadExistingMedia();
            }
            $this->importContent('page', $opts);
            $this->importContent('post', $opts);
            $this->importSubscribers();
            $this->report['comments_found'] = (int) $this->t('comments')->where('comment_approved', '1')->count();
        };

        if ($opts['dry_run']) {
            // Run everything inside a transaction and throw it away: a full rehearsal with a real report.
            DB::beginTransaction();
            try {
                $work();
            } finally {
                DB::rollBack();
            }
            $this->report['dry_run'] = true;
        } else {
            $work();
            ContentCache::bust();
        }

        return $this->report;
    }

    private function assertWordPress(): void
    {
        $tables = ['posts', 'postmeta', 'terms', 'term_taxonomy', 'term_relationships', 'users', 'options'];
        foreach ($tables as $t) {
            if (! $this->wp->getSchemaBuilder()->hasTable($this->p.$t)) {
                throw new \RuntimeException("WordPress table {$this->p}{$t} not found. Check WP_DB_* settings and --prefix.");
            }
        }
    }

    private function t(string $table)
    {
        return $this->wp->table($this->p.$table);
    }

    private function say(string $msg): void
    {
        if ($this->log) {
            ($this->log)($msg);
        }
    }

    /* ---------------------------------------------------------------- users */

    private function importUsers(): void
    {
        foreach ($this->t('users')->get() as $u) {
            $email = strtolower(trim($u->user_email)) ?: 'wp-user-'.$u->ID.'@invalid.local';
            $user = User::withTrashed()->where('wp_id', $u->ID)->first()
                ?? User::withTrashed()->where('email', $email)->first()
                ?? User::withTrashed()->where('name', $u->user_login)->where('role', 'editor')->first();
            if (! $user) {
                // Imported authors cannot log in until an admin sets a password and activates them.
                $user = new User(['email' => $email, 'password' => Str::random(40), 'role' => 'editor', 'is_active' => false]);
            }
            $user->fill(['name' => $u->display_name ?: $u->user_login, 'wp_id' => $u->ID]);
            $user->save();
            $this->userMap[$u->ID] = $user->id;
            $this->report['users']++;
        }
        $this->say("Users: {$this->report['users']}");
    }

    /* ---------------------------------------------------------------- terms */

    private function importTerms(): void
    {
        $rows = $this->t('term_taxonomy as tt')
            ->join($this->p.'terms as t', 't.term_id', '=', 'tt.term_id')
            ->whereIn('tt.taxonomy', ['category', 'post_tag'])
            ->get(['tt.term_taxonomy_id', 'tt.taxonomy', 'tt.description', 'tt.parent', 't.term_id', 't.name', 't.slug']);

        foreach ($rows as $r) {
            $name = html_entity_decode($r->name, ENT_QUOTES);
            if ($r->taxonomy === 'category') {
                $c = Category::where('wp_id', $r->term_id)->first() ?? Category::firstOrNew(['slug' => $r->slug]);
                $c->fill(['name' => $name, 'slug' => $c->slug ?: $r->slug, 'description' => $r->description ?: null, 'wp_id' => $r->term_id])->save();
                $this->catMap[$r->term_taxonomy_id] = $c->id;
                $this->report['categories']++;
            } else {
                $tag = Tag::where('wp_id', $r->term_id)->first() ?? Tag::firstOrNew(['slug' => $r->slug]);
                $tag->fill(['name' => $name, 'slug' => $tag->slug ?: $r->slug, 'wp_id' => $r->term_id])->save();
                $this->tagMap[$r->term_taxonomy_id] = $tag->id;
                $this->report['tags']++;
            }
        }
        // parents (by WordPress term id)
        foreach ($rows->where('taxonomy', 'category')->where('parent', '>', 0) as $r) {
            $parent = Category::where('wp_id', $r->parent)->first();
            if ($parent) {
                Category::where('wp_id', $r->term_id)->update(['parent_id' => $parent->id]);
            }
        }
        $this->say("Categories: {$this->report['categories']}, tags: {$this->report['tags']}");
    }

    /* ---------------------------------------------------------------- media */

    private function loadExistingMedia(): void
    {
        foreach (Media::whereNotNull('wp_id')->get() as $m) {
            $this->mediaMap[$m->wp_id] = $m->id;
            if ($m->wp_url && preg_match('#/uploads/(.+)$#', $m->wp_url, $mm)) {
                $this->fileMap[strtolower($mm[1])] = $m;
            }
        }
    }

    private function importMedia(array $opts): void
    {
        $this->loadExistingMedia();
        $meta = $this->metaFor('attachment', ['_wp_attached_file', '_wp_attachment_image_alt']);
        $parentTypes = $this->t('posts')->whereIn('post_type', ['page', 'post'])->pluck('post_type', 'ID');

        $attachments = $this->t('posts')->where('post_type', 'attachment')->where('post_mime_type', 'like', 'image/%')->orderBy('ID')->get();
        $total = $attachments->count();
        foreach ($attachments as $i => $a) {
            if (isset($this->mediaMap[$a->ID])) {
                $this->report['media_skipped']++;
                continue;
            }
            $file = $meta[$a->ID]['_wp_attached_file'] ?? null;
            if (! $file) {
                $file = preg_replace('#^.*/uploads/#', '', (string) $a->guid);
            }
            $wpUrl = rtrim($opts['site_url'], '/').'/wp-content/uploads/'.$file;

            $local = $this->locate($file, $wpUrl, $opts);
            if ($local && $opts['dry_run']) {
                // rehearsal: confirm the file is reachable, don't write images to disk
                $this->report['media']++;
                if (! empty($local['temp'])) {
                    @unlink($local['path']);
                }
                continue;
            }
            if (! $local) {
                $this->report['media_failed'][] = $file.' (file not found'.($opts['download'] ? ' locally or online' : ' — pass --uploads or --download').')';
                continue;
            }
            try {
                $folder = ($parentTypes[$a->post_parent] ?? 'post') === 'page' ? 'pages' : 'posts';
                $media = $this->media->storeFromPath($local['path'], basename($file), $folder, null, [
                    'alt' => Str::limit(trim($meta[$a->ID]['_wp_attachment_image_alt'] ?? ''), 250, '') ?: null,
                    'title' => Str::limit(html_entity_decode($a->post_title ?: pathinfo($file, PATHINFO_FILENAME)), 250, ''),
                    'caption' => $a->post_excerpt ?: null,
                    'wp_id' => $a->ID,
                    'wp_url' => $wpUrl,
                ], $a->post_date);
                $this->mediaMap[$a->ID] = $media->id;
                $this->fileMap[strtolower($file)] = $media;
                $this->report['media']++;
            } catch (Throwable $e) {
                $this->report['media_failed'][] = $file.' ('.$e->getMessage().')';
            } finally {
                if (! empty($local['temp'])) {
                    @unlink($local['path']);
                }
            }
            if (($i + 1) % 25 === 0) {
                $this->say('  media '.($i + 1)."/$total");
            }
        }
        $this->say("Media imported: {$this->report['media']}, already present: {$this->report['media_skipped']}, failed: ".count($this->report['media_failed']));
    }

    /** @return array{path:string,temp?:bool}|null */
    private function locate(string $file, string $url, array $opts): ?array
    {
        if ($opts['uploads']) {
            $path = rtrim($opts['uploads'], '/').'/'.$file;
            if (is_file($path)) {
                return ['path' => $path];
            }
        }
        if ($opts['download']) {
            try {
                $res = Http::timeout(60)->retry(2, 500)->get($url);
                if ($res->successful() && str_starts_with((string) $res->header('Content-Type'), 'image/')) {
                    $tmp = tempnam(sys_get_temp_dir(), 'wpimg');
                    file_put_contents($tmp, $res->body());

                    return ['path' => $tmp, 'temp' => true];
                }
            } catch (Throwable) {
                // fall through
            }
        }

        return null;
    }

    /* ---------------------------------------------------------------- pages & posts */

    private function importContent(string $type, array $opts): void
    {
        $rows = $this->t('posts')->where('post_type', $type)
            ->whereIn('post_status', ['publish', 'draft', 'pending', 'private', 'future'])
            ->orderBy('ID')->get();
        $meta = $this->metaFor($type, ['_thumbnail_id', '_wp_page_template', '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_aioseo_title', '_aioseo_description']);
        $seo = $this->aioseo();
        $front = (int) $this->option('page_on_front');
        $postsPage = (int) $this->option('page_for_posts');
        $terms = $type === 'post' ? $this->termsByObject() : [];

        foreach ($rows as $r) {
            if ($type === 'page' && in_array($r->post_name, ['sample-page'], true)) {
                continue;
            }
            $slug = $r->post_name !== '' ? urldecode($r->post_name) : (Str::slug(html_entity_decode($r->post_title)) ?: $type.'-'.$r->ID);
            $content = $this->convertContent($r->post_content);
            $m = $meta[$r->ID] ?? [];
            $seoTitle = $seo[$r->ID]['title'] ?? ($m['_yoast_wpseo_title'] ?? ($m['_aioseo_title'] ?? null));
            $seoDesc = $seo[$r->ID]['description'] ?? ($m['_yoast_wpseo_metadesc'] ?? ($m['_aioseo_description'] ?? null));
            $seoTitle = $this->cleanSeo($seoTitle);
            $seoDesc = $this->cleanSeo($seoDesc);
            $status = $r->post_status === 'publish' || $r->post_status === 'future' ? 'published' : 'draft';
            $date = $r->post_date && $r->post_date !== '0000-00-00 00:00:00' ? $r->post_date : null;
            $featured = isset($m['_thumbnail_id']) ? ($this->mediaMap[(int) $m['_thumbnail_id']] ?? null) : null;

            if ($type === 'page') {
                $page = Page::withTrashed()->where('wp_id', $r->ID)->first() ?? Page::withTrashed()->where('slug', $slug)->first();
                $isNew = ! $page;
                $page ??= new Page(['slug' => $this->freeSlug($slug, Page::class)]);
                $template = match (true) {
                    $r->ID === $front => 'home',
                    $r->ID === $postsPage => 'blog',
                    default => $page->template ?: (in_array($slug, ['about', 'designer', 'contact', 'blog'], true) ? $slug : 'default'),
                };
                $fill = [
                    'title' => html_entity_decode($r->post_title, ENT_QUOTES), 'template' => $template, 'status' => $status,
                    'published_at' => $date, 'menu_order' => $r->menu_order, 'wp_id' => $r->ID, 'featured_media_id' => $featured ?? $page->featured_media_id,
                    'seo_title' => $seoTitle ?? $page->seo_title, 'seo_description' => $seoDesc ?? $page->seo_description,
                    'author_id' => $this->userMap[$r->post_author] ?? $page->author_id,
                ];
                // Pages built with the theme's page builder are re-created as React templates; keep their
                // curated layout unless asked to overwrite. New pages always take the WordPress content.
                if ($isNew || $opts['overwrite_pages']) {
                    $fill['content'] = $content;
                }
                $page->fill($fill)->save();
                $isNew ? $this->report['pages_created']++ : $this->report['pages_updated']++;
                $this->trackSlugChange($r->post_name, $page->slug);
            } else {
                $post = Post::withTrashed()->where('wp_id', $r->ID)->first() ?? Post::withTrashed()->where('slug', $slug)->first();
                $isNew = ! $post;
                $post ??= new Post(['slug' => $this->freeSlug($slug, Post::class)]);
                $post->fill([
                    'title' => html_entity_decode($r->post_title, ENT_QUOTES),
                    'excerpt' => $r->post_excerpt ? Html::text($r->post_excerpt) : null,
                    'content' => $content, 'status' => $status, 'published_at' => $date ?? $post->published_at,
                    'featured_media_id' => $featured, 'author_id' => $this->userMap[$r->post_author] ?? $post->author_id,
                    'seo_title' => $seoTitle, 'seo_description' => $seoDesc, 'wp_id' => $r->ID,
                ])->save();
                $cats = []; $tags = [];
                foreach ($terms[$r->ID] ?? [] as $ttid) {
                    if (isset($this->catMap[$ttid])) {
                        $cats[] = $this->catMap[$ttid];
                    } elseif (isset($this->tagMap[$ttid])) {
                        $tags[] = $this->tagMap[$ttid];
                    }
                }
                if ($cats) {
                    $post->categories()->sync($cats);
                }
                $post->tags()->sync($tags);
                $isNew ? $this->report['posts_created']++ : $this->report['posts_updated']++;
                $this->trackSlugChange($r->post_name, $post->slug);
            }
        }
        $this->say(ucfirst($type).'s: created '.$this->report[$type.'s_created'].', updated '.$this->report[$type.'s_updated']);
    }

    private function freeSlug(string $slug, string $model): string
    {
        $other = $model === Post::class ? Page::class : Post::class;
        $clash = $model::withTrashed()->where('slug', $slug)->exists() || $other::withTrashed()->where('slug', $slug)->exists();

        return $clash ? \App\Support\Slug::forContent($model, $slug) : $slug;
    }

    private function trackSlugChange(string $wpSlug, string $newSlug): void
    {
        $wpSlug = urldecode($wpSlug);
        if ($wpSlug !== '' && $wpSlug !== $newSlug) {
            Redirect::firstOrCreate(['from_path' => Redirect::normalizePath($wpSlug)], ['to_path' => '/'.$newSlug.'/', 'status_code' => 301]);
            $this->report['redirects']++;
        }
    }

    private function cleanSeo(?string $v): ?string
    {
        if (! $v) {
            return null;
        }
        // Drop unresolved SEO-plugin variables such as #post_title or %%sitename%%
        $v = trim(preg_replace('/(#[a-z_]+|%%[a-z_]+%%)/i', '', $v));

        return $v !== '' ? Str::limit(html_entity_decode($v, ENT_QUOTES), 490, '') : null;
    }

    /**
     * Turn stored WordPress content into clean HTML: strip page-builder shortcodes and block comments,
     * add paragraphs for classic-editor posts, and point every upload URL at the new media library.
     */
    public function convertContent(string $raw): string
    {
        $html = preg_replace('/<!--\s*\/?wp:[^>]*-->/', '', $raw); // Gutenberg block markers

        // [vc_single_image image="123"] → <img>
        $html = preg_replace_callback('/\[vc_single_image[^\]]*image="(\d+)"[^\]]*\]/', function ($m) {
            $media = isset($this->mediaMap[(int) $m[1]]) ? Media::find($this->mediaMap[(int) $m[1]]) : null;

            return $media ? '<p><img src="'.e($media->url).'" alt="'.e($media->alt ?? '').'" width="'.$media->width.'" height="'.$media->height.'"></p>' : '';
        }, (string) $html);
        // [caption ...]<img ...> text[/caption] → figure
        $html = preg_replace('/\[caption[^\]]*\](.*?)(<img[^>]+>|<a[^>]*>\s*<img[^>]+>\s*<\/a>)\s*(.*?)\[\/caption\]/s', '<figure class="wp-caption">$2<figcaption>$3</figcaption></figure>', $html);
        // any remaining shortcode: keep inner text, drop the tags
        $html = preg_replace('/\[\/?[a-z_][a-z0-9_\-]*(\s[^\]]*)?\]/i', '', $html);

        if (! preg_match('/<(p|div|figure|h[1-6]|ul|ol|table|blockquote)[\s>]/i', $html)) {
            $html = $this->autop($html);
        }

        $html = $this->rewriteUploadUrls($html);

        return Html::sanitize($html);
    }

    private function autop(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $paras = preg_split('/\n\s*\n/', $text);

        return implode("\n", array_map(fn ($p) => '<p>'.nl2br(trim($p), false).'</p>', array_filter($paras, fn ($p) => trim($p) !== '')));
    }

    private function rewriteUploadUrls(string $html): string
    {
        // remove WordPress srcset/sizes (they point at old resized copies); the new site builds its own
        $html = preg_replace('/\s(srcset|sizes)="[^"]*"/i', '', $html);

        return preg_replace_callback(
            '#(?:https?:)?//(?:www\.)?[a-z0-9.\-]+/wp-content/uploads/([^"\'\s)<>?]+)#i',
            function ($m) {
                $file = $m[1];
                $size = null;
                if (preg_match('/^(.*)-(\d+)x(\d+)(\.\w+)$/', $file, $mm)) {
                    $original = $mm[1].$mm[4];
                    $size = max((int) $mm[2], (int) $mm[3]);
                } else {
                    $original = $file;
                }
                $media = $this->fileMap[strtolower($original)] ?? $this->fileMap[strtolower($file)] ?? null;
                if (! $media) {
                    return $m[0]; // unknown file: leave the link (reported by the broken-image check)
                }
                if ($size) {
                    $variants = $media->variantUrls();
                    foreach (config('media.variants') as $name => $px) {
                        if ($size <= $px && isset($variants[$name])) {
                            return $variants[$name];
                        }
                    }
                }

                return $media->url;
            },
            $html
        );
    }

    /* ---------------------------------------------------------------- subscribers */

    private function importSubscribers(): void
    {
        // Easy Registration Forms keeps submissions as a custom post type with the fields in postmeta.
        $ids = $this->t('posts')->whereIn('post_type', ['erforms_submission', 'erforms-submission'])->pluck('post_date', 'ID');
        if ($ids->isNotEmpty()) {
            $values = $this->t('postmeta')->whereIn('post_id', $ids->keys())->get(['post_id', 'meta_value']);
            foreach ($values as $v) {
                foreach ($this->emailsIn((string) $v->meta_value) as $email) {
                    $s = Subscriber::firstOrCreate(['email' => $email], [
                        'status' => 'confirmed', 'source' => 'wordpress-import', 'confirmed_at' => $ids[$v->post_id] ?? now(), 'token' => Str::random(40),
                    ]);
                    $s->wasRecentlyCreated && $this->report['subscribers']++;
                }
            }
        }
        // Also check plugin tables if present (e.g. wp_erforms_submissions)
        foreach (['erforms_submissions', 'erf_submissions'] as $t) {
            if ($this->wp->getSchemaBuilder()->hasTable($this->p.$t)) {
                foreach ($this->t($t)->get() as $row) {
                    foreach ($this->emailsIn(json_encode($row)) as $email) {
                        $s = Subscriber::firstOrCreate(['email' => $email], ['status' => 'confirmed', 'source' => 'wordpress-import', 'confirmed_at' => now(), 'token' => Str::random(40)]);
                        $s->wasRecentlyCreated && $this->report['subscribers']++;
                    }
                }
            }
        }
        if ($ids->isEmpty()) {
            $this->report['warnings'][] = 'No Easy Registration Forms submissions found; export subscribers from the plugin as CSV if the list lives elsewhere.';
        }
        $this->say("Subscribers: {$this->report['subscribers']}");
    }

    /** @return array<int,string> */
    private function emailsIn(string $text): array
    {
        $text = @unserialize($text) !== false ? json_encode(unserialize($text)) : $text;
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $m);

        return array_values(array_unique(array_map('strtolower', $m[0])));
    }

    /* ---------------------------------------------------------------- helpers */

    /** @return array<int,array<string,string>> */
    private function metaFor(string $postType, array $keys): array
    {
        $rows = $this->t('postmeta as m')
            ->join($this->p.'posts as p', 'p.ID', '=', 'm.post_id')
            ->where('p.post_type', $postType)
            ->whereIn('m.meta_key', $keys)
            ->get(['m.post_id', 'm.meta_key', 'm.meta_value']);
        $out = [];
        foreach ($rows as $r) {
            $out[$r->post_id][$r->meta_key] = $r->meta_value;
        }

        return $out;
    }

    /** @return array<int,array<int,int>> object id => term_taxonomy ids */
    private function termsByObject(): array
    {
        $out = [];
        foreach ($this->t('term_relationships')->get(['object_id', 'term_taxonomy_id']) as $r) {
            $out[$r->object_id][] = $r->term_taxonomy_id;
        }

        return $out;
    }

    /** All in One SEO v4 stores titles/descriptions in its own table. */
    private function aioseo(): array
    {
        if (! $this->wp->getSchemaBuilder()->hasTable($this->p.'aioseo_posts')) {
            return [];
        }
        $out = [];
        foreach ($this->t('aioseo_posts')->get(['post_id', 'title', 'description']) as $r) {
            $out[$r->post_id] = ['title' => $r->title, 'description' => $r->description];
        }

        return $out;
    }

    private function option(string $name): ?string
    {
        return $this->t('options')->where('option_name', $name)->value('option_value');
    }
}
