<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Gallery;
use App\Models\Page;
use App\Models\Post;
use App\Services\SettingsService;
use App\Support\Html;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SeoFilesController extends Controller
{
    public function sitemap(): Response
    {
        $xml = Cache::remember('sitemap.xml.v'.\App\Support\ContentCache::version(), 3600, function () {
            $base = config('frontend.url');
            $urls = [];
            foreach (Page::published()->where('noindex', false)->orderBy('menu_order')->get() as $p) {
                $urls[] = ['loc' => $base.$p->path(), 'lastmod' => $p->updated_at, 'priority' => $p->template === 'home' ? '1.0' : '0.8'];
            }
            foreach (Post::published()->where('noindex', false)->orderByDesc('published_at')->get() as $p) {
                $urls[] = ['loc' => $base.$p->path(), 'lastmod' => $p->updated_at, 'priority' => '0.6',
                    'image' => $p->coverUrl()];
            }
            foreach (Gallery::published()->get() as $g) {
                $urls[] = ['loc' => $base.'/gallery/'.$g->slug.'/', 'lastmod' => $g->updated_at, 'priority' => '0.5'];
            }

            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">'."\n";
            foreach ($urls as $u) {
                $out .= '  <url><loc>'.e($u['loc']).'</loc><lastmod>'.$u['lastmod']?->toAtomString().'</lastmod><priority>'.$u['priority'].'</priority>';
                if (! empty($u['image'])) {
                    $out .= '<image:image><image:loc>'.e($u['image']).'</image:loc></image:image>';
                }
                $out .= "</url>\n";
            }

            return $out.'</urlset>';
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $base = config('frontend.url');
        $lines = app()->environment('production')
            ? ['User-agent: *', 'Disallow: /api/', 'Disallow: /admin', 'Disallow: /*?q=', '', 'Sitemap: '.$base.'/sitemap.xml']
            : ['User-agent: *', 'Disallow: /']; // never let staging/dev get indexed

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function feed(SettingsService $settings): Response
    {
        $base = config('frontend.url');
        $name = $settings->get('site_name', 'Finns Kerrisdale Ladies');
        $posts = Post::published()->orderByDesc('published_at')->limit(20)->get();

        $items = '';
        foreach ($posts as $p) {
            $items .= '<item><title>'.e($p->title ?: $name).'</title><link>'.e($base.$p->path()).'</link>'
                .'<guid isPermaLink="true">'.e($base.$p->path()).'</guid><pubDate>'.$p->published_at->toRssString().'</pubDate>'
                .'<description>'.e(Html::excerpt($p->content ?? '', 300)).'</description></item>';
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>'.e($name).'</title><link>'.e($base.'/').'</link>'
            .'<description>'.e($settings->get('tagline', '')).'</description><language>en-US</language>'.$items.'</channel></rss>';

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
}
