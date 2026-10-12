<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_seeded_site_matches_wordpress_inventory(): void
    {
        $this->assertSame(36, Post::published()->count());
        $this->getJson('/api/public/posts')->assertOk()
            ->assertJsonPath('meta.total', 36)->assertJsonPath('meta.last_page', 4)->assertJsonCount(10, 'data')
            ->assertJsonPath('data.0.slug', 'more-from-shan-collections');
        $this->getJson('/api/public/posts?page=4')->assertJsonCount(6, 'data')->assertJsonPath('data.5.slug', 'save-the-queen-spring-2015');
    }

    public function test_resolve_page_post_redirect_and_404(): void
    {
        $this->getJson('/api/public/resolve/about')->assertOk()->assertJsonPath('data.type', 'page')->assertJsonPath('data.item.template', 'about');
        $this->getJson('/api/public/resolve/riani-s21')->assertOk()->assertJsonPath('data.type', 'post')
            ->assertJsonPath('data.item.previous.slug', 'spring-21-fringe-coat-from-luisa-cerano')
            ->assertJsonPath('data.item.next.slug', 'spring-2024')
            ->assertJsonPath('data.item.categories.0.name', 'Uncategorized');
        // second call is served from cache and must be identical
        $this->getJson('/api/public/resolve/riani-s21')->assertJsonPath('data.item.categories.0.slug', 'uncategorized');
        $this->getJson('/api/public/resolve/sample-page')->assertOk()->assertJsonPath('data.type', 'redirect')->assertJsonPath('data.to', '/');
        $this->getJson('/api/public/resolve/does-not-exist')->assertNotFound()->assertJsonPath('success', false);
    }

    public function test_drafts_are_hidden(): void
    {
        Post::where('slug', 'riani-s21')->update(['status' => 'draft']);
        \App\Support\ContentCache::bust();
        $this->getJson('/api/public/posts/riani-s21')->assertNotFound();
        $this->getJson('/api/public/posts')->assertJsonPath('meta.total', 35);
    }

    public function test_cover_image_falls_back_to_first_content_image(): void
    {
        $this->getJson('/api/public/posts/cambio-24')->assertOk()
            ->assertJsonPath('data.cover_url', 'https://finnskerrisdaleladies.com/wp-content/uploads/2024/01/thumbnail_IMG_5844-685x1024.jpg');
    }

    public function test_home_designer_site_endpoints(): void
    {
        $this->getJson('/api/public/pages/home')->assertOk()->assertJsonPath('data.template', 'home')->assertJsonCount(3, 'data.latest_posts');
        $this->getJson('/api/public/pages/designer')->assertOk()->assertJsonCount(4, 'data.brands');
        $this->getJson('/api/public/site')->assertOk()->assertJsonCount(5, 'data.menu')->assertJsonPath('data.menu.3.label', 'Latest News');
    }

    public function test_admin_edit_is_visible_publicly_immediately(): void
    {
        $this->getJson('/api/public/posts/cambio-24')->assertJsonPath('data.title', 'CAMBIO ’24'); // cached now
        $this->actingAsAdmin();
        $id = Post::where('slug', 'cambio-24')->value('id');
        $this->putJson("/api/admin/posts/$id", ['title' => 'CAMBIO 2024'])->assertOk();
        $this->getJson('/api/public/posts/cambio-24')->assertJsonPath('data.title', 'CAMBIO 2024');
    }

    public function test_subscribe_and_contact_forms(): void
    {
        $this->postJson('/api/public/subscribers', ['email' => 'Fan@Example.com'])->assertCreated();
        $this->postJson('/api/public/subscribers', ['email' => 'fan@example.com'])->assertOk(); // no duplicate, same message
        $this->assertDatabaseCount('subscribers', 1);
        $this->postJson('/api/public/subscribers', ['email' => 'nope'])->assertStatus(422);
        $this->postJson('/api/public/subscribers', ['email' => 'bot@example.com', 'website' => 'http://spam'])->assertStatus(422);
        $this->postJson('/api/public/contact', ['name' => 'A', 'email' => 'a@example.com', 'message' => 'Hello there'])->assertCreated();
    }

    public function test_cors_allows_only_configured_origins(): void
    {
        $this->call('OPTIONS', '/api/public/posts', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:5173', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');

        $this->call('OPTIONS', '/api/admin/posts', [], [], [], [
            'HTTP_ORIGIN' => 'http://localhost:5174', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'DELETE', 'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'authorization',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5174');

        $res = $this->call('OPTIONS', '/api/public/posts', [], [], [], [
            'HTTP_ORIGIN' => 'https://evil.example', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'GET',
        ]);
        $this->assertNull($res->headers->get('Access-Control-Allow-Origin'));
    }
}
