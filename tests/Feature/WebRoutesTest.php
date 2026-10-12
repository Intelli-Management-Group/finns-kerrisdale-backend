<?php

namespace Tests\Feature;

use App\Models\Media;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_spa_shell_gets_per_url_seo_tags(): void
    {
        $dist = sys_get_temp_dir().'/fkl-dist-'.uniqid();
        mkdir($dist);
        file_put_contents("$dist/index.html", '<!doctype html><html><head><title>x</title><!--seo--></head><body><div id="root"></div></body></html>');
        config(['frontend.dist_path' => $dist]);

        $html = $this->get('/riani-s21/')->assertOk()->getContent();
        $this->assertStringContainsString('<title>RIANI S’21 - Finns Kerrisdale Ladies</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost:5173/riani-s21/">', $html);
        $this->assertStringContainsString('property="og:type" content="article"', $html);
        $this->assertStringContainsString('og:image" content="https://finnskerrisdaleladies.com/wp-content/uploads/2021/04/thumbnail_IMG_6310-196x300.jpg"', $html);
        $this->assertStringContainsString('"@type":"BlogPosting"', $html);

        $this->assertStringContainsString('"@type":"ClothingStore"', $this->get('/')->getContent());
        app(\App\Services\SettingsService::class)->set('google_site_verification', '<meta name="google-site-verification" content="abc123" />', 'seo');
        $this->assertStringContainsString('<meta name="google-site-verification" content="abc123">', $this->get('/')->getContent());
        $this->assertStringContainsString('noindex', $this->get('/blog/page/2/')->getContent());
        $this->get('/blog/page/9/')->assertNotFound();
        $this->get('/no-such-page/')->assertNotFound();

        // category archives (old /category/uncategorized/ is a seeded 301 to /blog/)
        $this->get('/category/uncategorized/')->assertStatus(301);
        \App\Models\Category::create(['name' => 'Riani', 'slug' => 'riani']);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost:5173/category/riani/">', $this->get('/category/riani/')->assertOk()->getContent());
        $this->get('/category/nope/')->assertNotFound();
    }

    public function test_legacy_wordpress_urls(): void
    {
        $base = rtrim(config('app.url'), '/');
        $this->get('/sample-page/')->assertStatus(301)->assertHeader('Location', $base.'/');
        $this->get('/category/uncategorized/')->assertStatus(301)->assertHeader('Location', $base.'/blog/');
        $this->get('/?p=393')->assertStatus(301)->assertHeader('Location', $base.'/riani-s21/');
        $this->get('/?page_id=55')->assertStatus(301)->assertHeader('Location', $base.'/about/');
        $this->get('/wp-login.php')->assertStatus(410);
        $this->get('/wp-admin/')->assertStatus(410);

        Media::create(['disk' => 'public', 'folder' => 'posts', 'path' => 'uploads/posts/2024/03/a.webp', 'filename' => 'a.webp', 'mime_type' => 'image/webp', 'extension' => 'webp',
            'wp_url' => 'https://finnskerrisdaleladies.com/wp-content/uploads/2024/03/thumbnail_Whitagram-Image-34.jpg']);
        $this->get('/wp-content/uploads/2024/03/thumbnail_Whitagram-Image-34-300x300.jpg')->assertStatus(301)->assertRedirectContains('/storage/uploads/posts/2024/03/a.webp');
    }

    public function test_sitemap_robots_feed(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();
        $this->assertSame(5 + 36, substr_count($xml, '<url>'));
        $this->assertStringContainsString('<loc>http://localhost:5173/riani-s21/</loc>', $xml);

        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /');
        $this->get('/feed')->assertOk()->assertSee('<rss', false)->assertSee('MORE FROM SHAN COLLECTIONS');
    }
}
