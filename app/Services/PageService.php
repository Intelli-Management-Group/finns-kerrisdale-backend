<?php

namespace App\Services;

use App\Models\Page;
use App\Models\Redirect;
use App\Models\User;
use App\Support\ContentCache;
use App\Support\Html;
use App\Support\Slug;
use Illuminate\Support\Arr;

class PageService
{
    public function save(Page $page, array $data, ?User $user = null): Page
    {
        $fill = Arr::only($data, [
            'title', 'template', 'content', 'sections', 'featured_media_id', 'status', 'published_at', 'menu_order',
            'seo_title', 'seo_description', 'og_media_id', 'canonical_url', 'noindex',
        ]);
        if (array_key_exists('content', $fill)) {
            $fill['content'] = Html::sanitize($fill['content']);
        }
        $page->fill($fill);

        if (! $page->exists && $user) {
            $page->author_id = $user->id;
        }
        $oldPath = $page->exists && $page->getOriginal('status') === 'published' && $page->getOriginal('template') !== 'home' ? '/'.$page->getOriginal('slug').'/' : null;
        if (! empty($data['slug'])) {
            $page->slug = Slug::forContent(Page::class, $data['slug'], $page->id);
        } elseif (! $page->slug) {
            $page->slug = Slug::forContent(Page::class, $page->title, $page->id);
        }
        if ($page->status === 'published' && ! $page->published_at) {
            $page->published_at = now();
        }
        $page->save();
        if ($oldPath && $page->status === 'published' && $oldPath !== $page->path()) {
            Redirect::rememberMove($oldPath, $page->path());
        }
        ContentCache::bust();

        return $page->fresh(['featuredMedia', 'ogMedia']);
    }
}
