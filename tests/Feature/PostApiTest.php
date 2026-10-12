<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_crud_with_categories_tags_and_sanitizing(): void
    {
        $admin = $this->actingAsAdmin();
        $cat = Category::create(['name' => 'Riani', 'slug' => 'riani']);

        $res = $this->postJson('/api/admin/posts', [
            'title' => 'Riani Spring 2027',
            'content' => '<p onclick="steal()">Hello</p><script>alert(1)</script>',
            'status' => 'draft',
            'category_ids' => [$cat->id],
            'tags' => ['Spring', 'Navy'],
            'seo_title' => 'Riani Spring',
        ])->assertCreated()->assertJsonPath('data.slug', 'riani-spring-2027');

        $id = $res->json('data.id');
        $post = Post::find($id);
        $this->assertSame('<p>Hello</p>', $post->content);
        $this->assertSame($admin->id, $post->author_id);
        $this->assertCount(2, $post->tags);
        $this->assertNull($post->published_at);

        $this->patchJson("/api/admin/posts/$id/status", ['status' => 'published'])->assertOk()->assertJsonPath('message', 'Post published');
        $this->assertNotNull($post->fresh()->published_at);

        $this->putJson("/api/admin/posts/$id", ['title' => 'Changed title'])->assertOk()->assertJsonPath('data.slug', 'riani-spring-2027');
        $this->getJson("/api/admin/posts/$id")->assertOk()->assertJsonPath('data.title', 'Changed title');

        $this->deleteJson("/api/admin/posts/$id")->assertOk();
        $this->assertSoftDeleted('posts', ['id' => $id]);
        $this->postJson("/api/admin/posts/$id/restore")->assertOk();
        $this->assertNotSoftDeleted('posts', ['id' => $id]);
    }

    public function test_slug_never_collides_with_a_page(): void
    {
        $this->actingAsAdmin();
        Page::create(['title' => 'About', 'slug' => 'about', 'status' => 'published']);
        $this->postJson('/api/admin/posts', ['title' => 'About', 'status' => 'draft'])
            ->assertCreated()->assertJsonPath('data.slug', 'about-2');
        $this->postJson('/api/admin/posts', ['title' => 'Blog', 'status' => 'draft'])
            ->assertCreated()->assertJsonPath('data.slug', 'blog-2'); // reserved route
    }

    public function test_search_filter_sort_and_pagination(): void
    {
        $this->actingAsAdmin();
        foreach (range(1, 25) as $i) {
            Post::create(['title' => "Post $i", 'slug' => "post-$i", 'status' => $i % 2 ? 'published' : 'draft', 'published_at' => now()->subDays($i)]);
        }
        $this->getJson('/api/admin/posts?per_page=10&page=2')
            ->assertOk()->assertJsonPath('meta.total', 25)->assertJsonPath('meta.current_page', 2)->assertJsonCount(10, 'data');
        $this->getJson('/api/admin/posts?status=draft')->assertJsonPath('meta.total', 12);
        $this->getJson('/api/admin/posts?q=Post 2')->assertJsonPath('meta.total', 7); // 2, 20-25
        $this->getJson('/api/admin/posts?sort=title&per_page=1')->assertJsonPath('data.0.title', 'Post 1');
    }

    public function test_validation_errors(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/admin/posts', ['status' => 'live', 'slug' => 'Bad Slug!'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['title', 'status', 'slug']]);
    }

    public function test_bulk_publish_and_delete(): void
    {
        $this->actingAsAdmin();
        $a = Post::create(['title' => 'A', 'slug' => 'a', 'status' => 'draft']);
        $b = Post::create(['title' => 'B', 'slug' => 'b', 'status' => 'draft']);
        $this->postJson('/api/admin/posts/bulk', ['ids' => [$a->id, $b->id], 'action' => 'publish'])->assertOk();
        $this->assertSame(2, Post::where('status', 'published')->count());
        $this->postJson('/api/admin/posts/bulk', ['ids' => [$a->id], 'action' => 'delete'])->assertOk();
        $this->assertSoftDeleted('posts', ['id' => $a->id]);
    }

    public function test_only_admin_can_force_delete(): void
    {
        $p = Post::create(['title' => 'A', 'slug' => 'a', 'status' => 'draft']);
        $p->delete();
        $this->actingAsEditor();
        $this->deleteJson("/api/admin/posts/{$p->id}/force")->assertStatus(403);
        $this->actingAsAdmin();
        $this->deleteJson("/api/admin/posts/{$p->id}/force")->assertOk();
        $this->assertDatabaseMissing('posts', ['id' => $p->id]);
    }

    public function test_changing_a_live_slug_creates_a_301(): void
    {
        $this->actingAsAdmin();
        $id = $this->postJson('/api/admin/posts', ['title' => 'Old name', 'status' => 'published'])->json('data.id');
        $this->putJson("/api/admin/posts/$id", ['slug' => 'new-name'])->assertOk();
        $this->assertDatabaseHas('redirects', ['from_path' => '/old-name/', 'to_path' => '/new-name/', 'status_code' => 301]);

        // renaming back removes the loop and re-points the chain
        $this->putJson("/api/admin/posts/$id", ['slug' => 'old-name'])->assertOk();
        $this->assertDatabaseMissing('redirects', ['from_path' => '/old-name/']);
        $this->assertDatabaseHas('redirects', ['from_path' => '/new-name/', 'to_path' => '/old-name/']);
    }
}
