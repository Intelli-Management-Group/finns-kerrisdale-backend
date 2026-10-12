<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_crud_and_home_protection(): void
    {
        $this->actingAsAdmin();
        $id = $this->postJson('/api/admin/pages', ['title' => 'Our Story', 'status' => 'published', 'template' => 'default', 'content' => '<h2>Hi</h2>'])
            ->assertCreated()->assertJsonPath('data.slug', 'our-story')->json('data.id');
        $this->putJson("/api/admin/pages/$id", ['seo_description' => 'About us'])->assertOk()->assertJsonPath('data.seo.description', 'About us');
        $this->patchJson("/api/admin/pages/$id/status", ['status' => 'draft'])->assertOk();
        $this->deleteJson("/api/admin/pages/$id")->assertOk();

        $home = Page::create(['title' => 'Home', 'slug' => 'home', 'template' => 'home', 'status' => 'published']);
        $this->deleteJson("/api/admin/pages/{$home->id}")->assertStatus(422);
    }

    public function test_category_tag_brand_gallery_crud(): void
    {
        $this->actingAsAdmin();
        $cat = $this->postJson('/api/admin/categories', ['name' => 'Luisa Cerano'])->assertCreated()->assertJsonPath('data.slug', 'luisa-cerano')->json('data.id');
        $this->putJson("/api/admin/categories/$cat", ['name' => 'LC'])->assertOk()->assertJsonPath('data.name', 'LC');
        $this->getJson('/api/admin/categories?all=1')->assertOk()->assertJsonCount(1, 'data');
        $this->deleteJson("/api/admin/categories/$cat")->assertOk();

        $tag = $this->postJson('/api/admin/tags', ['name' => 'Spring 24'])->assertCreated()->json('data.id');
        $this->deleteJson("/api/admin/tags/$tag")->assertOk();

        $b1 = $this->postJson('/api/admin/brands', ['name' => 'Riani', 'website_url' => 'https://riani.de'])->assertCreated()->json('data.id');
        $b2 = $this->postJson('/api/admin/brands', ['name' => 'Cambio'])->assertCreated()->json('data.id');
        $this->putJson('/api/admin/brands/order', ['ids' => [$b2, $b1]])->assertOk();
        $this->getJson('/api/admin/brands?all=1')->assertJsonPath('data.0.name', 'Cambio');

        $g = $this->postJson('/api/admin/galleries', ['title' => 'Spring Windows', 'status' => 'published'])->assertCreated()->json('data.id');
        $this->getJson("/api/admin/galleries/$g")->assertOk()->assertJsonPath('data.slug', 'spring-windows');
    }

    public function test_user_management_rules(): void
    {
        $me = $this->actingAsAdmin();
        $id = $this->postJson('/api/admin/users', ['name' => 'Patty', 'email' => 'Patty@Example.com', 'password' => 'Password1234', 'role' => 'editor'])
            ->assertCreated()->assertJsonPath('data.email', 'patty@example.com')->json('data.id');
        $this->postJson('/api/admin/users', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'short', 'role' => 'editor'])->assertStatus(422);
        $this->putJson("/api/admin/users/{$me->id}", ['role' => 'editor'])->assertStatus(422);
        $this->deleteJson("/api/admin/users/{$me->id}")->assertStatus(422);
        $this->deleteJson("/api/admin/users/$id")->assertOk();
        $this->assertSoftDeleted('users', ['id' => $id]);
    }

    public function test_settings_and_menu(): void
    {
        $this->actingAsAdmin();
        Setting::create(['key' => 'phone', 'value' => '1', 'group' => 'contact']);
        Setting::create(['key' => 'notification_email', 'value' => 'a@b.com', 'group' => 'forms', 'is_public' => false]);

        $this->putJson('/api/admin/settings', ['settings' => ['phone' => '(604) 266-8358', 'unknown_key' => 'x']])->assertOk();
        $this->assertSame('(604) 266-8358', Setting::where('key', 'phone')->first()->value);
        $this->assertNull(Setting::where('key', 'unknown_key')->first());
        $this->putJson('/api/admin/settings', ['settings' => ['notification_email' => 'not-an-email']])->assertStatus(422);

        $this->putJson('/api/admin/menu', ['items' => [['label' => 'Home', 'url' => '/'], ['label' => 'Blog', 'url' => '/blog/']]])
            ->assertOk()->assertJsonCount(2, 'data');

        // private setting is not exposed publicly
        $this->getJson('/api/public/site')->assertOk()->assertJsonPath('data.settings.phone', '(604) 266-8358')
            ->assertJsonMissingPath('data.settings.notification_email');
    }

    public function test_dashboard(): void
    {
        $this->actingAsEditor();
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonStructure(['data' => ['stats' => ['posts_published', 'contact_unread'], 'recent_posts', 'recent_submissions']]);
    }

    public function test_inbox_endpoints(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/public/contact', ['name' => 'Jane', 'email' => 'jane@example.com', 'message' => 'Do you have size 8?'])->assertCreated();
        $list = $this->getJson('/api/admin/contact-submissions')->assertOk()->assertJsonPath('meta.unread', 1);
        $id = $list->json('data.0.id');
        $this->getJson("/api/admin/contact-submissions/$id")->assertOk();
        $this->getJson('/api/admin/contact-submissions')->assertJsonPath('meta.unread', 0);
        $this->patchJson("/api/admin/contact-submissions/$id", ['read' => false])->assertOk();
        $this->deleteJson("/api/admin/contact-submissions/$id")->assertOk();

        $this->postJson('/api/public/subscribers', ['email' => 'fan@example.com'])->assertCreated();
        $this->getJson('/api/admin/subscribers')->assertOk()->assertJsonPath('meta.total', 1);
        $csv = $this->get('/api/admin/subscribers/export');
        $csv->assertOk();
        $this->assertStringContainsString('fan@example.com', $csv->streamedContent());
    }
}
