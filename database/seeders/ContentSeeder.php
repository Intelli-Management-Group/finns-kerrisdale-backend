<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Support\Html;
use Illuminate\Database\Seeder;

/**
 * Seeds pages and posts from a snapshot of the live site (database/seeders/data).
 * Safe to re-run: rows are matched by wp_id/slug and only created when missing.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('name', 'finnsadmin')->first();
        $admin = User::where('role', 'admin')->first();

        $uncategorized = Category::firstOrCreate(['slug' => 'uncategorized'], ['name' => 'Uncategorized', 'wp_id' => 1]);
        Category::firstOrCreate(['slug' => 'blog'], ['name' => 'Blog', 'wp_id' => 3]);

        foreach (json_decode(file_get_contents(__DIR__.'/data/pages.json'), true) as $p) {
            Page::firstOrCreate(['slug' => $p['slug']], [
                'wp_id' => $p['wp_id'], 'title' => $p['title'], 'template' => $p['template'], 'menu_order' => $p['menu_order'],
                'content' => Html::sanitize($p['content']), 'status' => 'published', 'published_at' => $p['published_at'],
                'seo_title' => $p['seo_title'], 'seo_description' => $p['seo_description'], 'author_id' => $admin?->id,
            ]);
        }

        $posts = require __DIR__.'/data/posts.php';
        foreach ($posts as $p) {
            $post = Post::withTrashed()->where('slug', $p['slug'])->first();
            if ($post) {
                continue;
            }
            $post = Post::create([
                'wp_id' => $p['wp_id'], 'title' => $p['title'], 'slug' => $p['slug'],
                'content' => Html::sanitize($p['content']), 'status' => 'published', 'published_at' => $p['date'],
                'author_id' => $author?->id,
            ]);
            $post->categories()->sync([$uncategorized->id]);
        }
    }
}
