<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Gallery;
use App\Models\Tag;
use App\Models\Page;
use App\Models\Post;
use App\Support\Html;

/**
 * Works out <title>, description, canonical, Open Graph and JSON-LD for any public URL.
 */
class SeoService
{
    public function __construct(private SettingsService $settings) {}

    /** @return array{status:int,title:string,description:string,canonical:string,image:?string,type:string,noindex:bool,jsonld:array} */
    public function forPath(string $path): array
    {
        $base = config('frontend.url');
        $siteName = $this->settings->get('site_name', 'Finns Kerrisdale Ladies');
        $suffix = ' - '.$siteName;
        $defaultDesc = $this->settings->get('seo_default_description', '');
        $defaultImage = $this->settings->get('og_default_image');
        $path = '/'.trim($path, '/');
        $path = $path === '/' ? '/' : $path.'/';

        $meta = [
            'status' => 200, 'title' => $siteName, 'description' => $defaultDesc, 'canonical' => $base.$path,
            'image' => $defaultImage, 'type' => 'website', 'noindex' => false, 'jsonld' => [],
        ];

        if ($path === '/') {
            $home = Page::published()->where('template', 'home')->first();
            $meta['title'] = ($home?->seo_title ?: 'Home').$suffix;
            $meta['description'] = $home?->seo_description ?: Html::excerpt($home?->content ?? '', 300) ?: $defaultDesc;
            $meta['jsonld'][] = $this->localBusiness();

            return $meta;
        }

        if (preg_match('#^/blog/(?:page/(\d+)/)?$#', $path, $m)) {
            $n = (int) ($m[1] ?? 1);
            $blog = Page::published()->where('template', 'blog')->first();
            $meta['title'] = ($blog?->seo_title ?: ($blog?->title ?? 'Blog')).($n > 1 ? " - Page $n" : '').$suffix;
            $meta['description'] = $blog?->seo_description ?: $this->settings->get('tagline', $defaultDesc);
            $meta['canonical'] = $base.'/blog/';
            $meta['noindex'] = $n > 1; // same as the WordPress site: paginated archive pages are noindex
            $last = (int) ceil(Post::published()->count() / max(1, (int) $this->settings->get('posts_per_page', 10)));
            if ($n > max(1, $last)) {
                $meta['status'] = 404;
            }

            return $meta;
        }

        if (preg_match('#^/(category|tag)/([a-z0-9\-_]+)/(?:page/(\d+)/)?$#i', $path, $m)) {
            $model = $m[1] === 'category' ? Category::class : Tag::class;
            $term = $model::query()->where('slug', strtolower($m[2]))->first();
            if (! $term) {
                return array_merge($meta, ['status' => 404, 'title' => 'Page not found'.$suffix, 'noindex' => true]);
            }
            $n = (int) ($m[3] ?? 1);
            $meta['title'] = $term->name.($n > 1 ? " - Page $n" : '').$suffix;
            $meta['description'] = Html::excerpt($term->description ?? '', 160) ?: $defaultDesc;
            $meta['canonical'] = $base.'/'.$m[1].'/'.$term->slug.'/';
            $meta['noindex'] = $n > 1 || $m[1] === 'tag';

            return $meta;
        }

        if (preg_match('#^/gallery/(?:([a-z0-9\-]+)/)?$#', $path, $m)) {
            if (! empty($m[1])) {
                $g = Gallery::published()->where('slug', $m[1])->first();
                if (! $g) {
                    return array_merge($meta, ['status' => 404, 'title' => 'Page not found'.$suffix, 'noindex' => true]);
                }
                $meta['title'] = $g->title.$suffix;
                $meta['description'] = Html::excerpt($g->description ?? '', 160) ?: $defaultDesc;
                $meta['image'] = $g->cover?->url ?? $meta['image'];
            } else {
                $meta['title'] = 'Gallery'.$suffix;
            }

            return $meta;
        }

        if (preg_match('#^/([a-z0-9\-_]+)/$#i', $path, $m)) {
            $slug = strtolower($m[1]);
            if ($page = Page::published()->where('slug', $slug)->first()) {
                $meta['title'] = ($page->seo_title ?: $page->title).$suffix;
                $meta['description'] = $page->seo_description ?: (Html::excerpt($page->content ?? '', 300) ?: $defaultDesc);
                $meta['canonical'] = $page->canonical_url ?: $meta['canonical'];
                $meta['image'] = $page->ogMedia?->url ?? $page->featuredMedia?->url ?? $meta['image'];
                $meta['noindex'] = (bool) $page->noindex;
                if ($page->template === 'contact') {
                    $meta['jsonld'][] = $this->localBusiness();
                }

                return $meta;
            }
            if ($post = Post::published()->where('slug', $slug)->first()) {
                $meta['title'] = ($post->seo_title ?: ($post->title ?: $siteName)).($post->seo_title ? '' : $suffix);
                $meta['description'] = $post->seo_description ?: $post->summary(155);
                $meta['canonical'] = $post->canonical_url ?: $meta['canonical'];
                $meta['image'] = $post->ogMedia?->url ?? $post->coverUrl() ?? $meta['image'];
                $meta['type'] = 'article';
                $meta['noindex'] = (bool) $post->noindex;
                $meta['jsonld'][] = [
                    '@context' => 'https://schema.org', '@type' => 'BlogPosting',
                    'headline' => $post->title, 'datePublished' => $post->published_at?->toIso8601String(),
                    'dateModified' => $post->updated_at?->toIso8601String(), 'image' => $meta['image'],
                    'author' => ['@type' => 'Organization', 'name' => $siteName],
                    'mainEntityOfPage' => $meta['canonical'],
                ];

                return $meta;
            }
        }

        return array_merge($meta, ['status' => 404, 'title' => 'Page not found'.$suffix, 'noindex' => true]);
    }

    public function localBusiness(): array
    {
        $s = $this->settings->all();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'ClothingStore',
            'name' => $s['site_name'] ?? 'Finns Kerrisdale Ladies',
            'url' => config('frontend.url').'/',
            'telephone' => $s['phone'] ?? null,
            'email' => $s['email'] ?? null,
            'image' => $s['logo_url'] ?? null,
            'address' => array_filter([
                '@type' => 'PostalAddress',
                'streetAddress' => $s['address_street'] ?? null,
                'addressLocality' => $s['address_city'] ?? null,
                'addressRegion' => $s['address_region'] ?? null,
                'postalCode' => $s['address_postal_code'] ?? null,
                'addressCountry' => $s['address_country'] ?? null,
            ]),
            'openingHoursSpecification' => $s['opening_hours_schema'] ?? null,
        ]);
    }
}
