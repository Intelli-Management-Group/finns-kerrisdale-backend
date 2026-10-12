<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Tag;
use App\Models\Redirect;
use App\Models\User;
use App\Support\ContentCache;
use App\Support\Html;
use App\Support\Slug;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostService
{
    public function save(Post $post, array $data, ?User $user = null): Post
    {
        return DB::transaction(function () use ($post, $data, $user) {
            $fill = Arr::only($data, [
                'title', 'excerpt', 'content', 'featured_media_id', 'author_id', 'author_name', 'status',
                'published_at', 'is_featured', 'seo_title', 'seo_description', 'og_media_id', 'canonical_url', 'noindex',
            ]);

            if (array_key_exists('content', $fill)) {
                $fill['content'] = Html::sanitize($fill['content']);
            }
            if (array_key_exists('title', $fill)) {
                $fill['title'] = trim((string) $fill['title']);
            }

            $post->fill($fill);

            if (! $post->exists && ! $post->author_id && $user) {
                $post->author_id = $user->id;
            }

            // Slug: explicit value wins; otherwise derive from the title once (never silently change a live URL).
            $oldPath = $post->exists && $post->getOriginal('status') === 'published' ? '/'.$post->getOriginal('slug').'/' : null;
            if (! empty($data['slug'])) {
                $post->slug = Slug::forContent(Post::class, $data['slug'], $post->id);
            } elseif (! $post->slug) {
                $post->slug = Slug::forContent(Post::class, $post->title ?: 'post-'.Str::lower(Str::random(6)), $post->id);
            }

            if ($post->status === 'published' && ! $post->published_at) {
                $post->published_at = now();
            }

            $post->save();
            if ($oldPath && $post->status === 'published' && $oldPath !== $post->path()) {
                Redirect::rememberMove($oldPath, $post->path());
            }

            if (array_key_exists('category_ids', $data)) {
                $post->categories()->sync($data['category_ids'] ?? []);
            }
            if (array_key_exists('tags', $data)) {
                $post->tags()->sync($this->tagIds($data['tags'] ?? []));
            }
            if (array_key_exists('image_ids', $data)) {
                $sync = [];
                foreach (array_values($data['image_ids'] ?? []) as $i => $id) {
                    $sync[$id] = ['sort_order' => $i];
                }
                $post->images()->sync($sync);
            }

            ContentCache::bust();

            return $post->fresh(['categories', 'tags', 'images', 'featuredMedia', 'ogMedia', 'author']);
        });
    }

    /** @param array<int,string|int> $tags names or ids */
    private function tagIds(array $tags): array
    {
        $ids = [];
        foreach ($tags as $tag) {
            if (is_numeric($tag)) {
                $ids[] = (int) $tag;
                continue;
            }
            $name = trim((string) $tag);
            if ($name === '') {
                continue;
            }
            $slug = Str::slug($name);
            $ids[] = Tag::query()->firstOrCreate(['slug' => $slug], ['name' => $name])->id;
        }

        return array_values(array_unique($ids));
    }
}
