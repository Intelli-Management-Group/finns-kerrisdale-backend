<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_upload_resizes_compresses_and_converts_to_webp(): void
    {
        $this->actingAsAdmin();
        config(['media.max_width' => 2000, 'media.max_height' => 2000, 'media.quality' => 80, 'media.format' => 'webp']);
        $file = $this->fakeJpeg(3000, 2000, 'Riani Spring.JPG');
        $originalSize = $file->getSize();

        $res = $this->postJson('/api/admin/media', ['file' => $file, 'folder' => 'posts', 'alt' => 'Navy blazer'])
            ->assertCreated()
            ->assertJsonPath('data.width', 2000)
            ->assertJsonPath('data.height', 1333)
            ->assertJsonPath('data.mime_type', 'image/webp')
            ->assertJsonPath('data.alt', 'Navy blazer');

        $media = Media::first();
        $this->assertMatchesRegularExpression('#^uploads/posts/\d{4}/\d{2}/riani-spring-[a-z0-9]{6}\.webp$#', $media->path);
        Storage::disk('public')->assertExists($media->path);
        $this->assertLessThan($originalSize, $media->size_bytes);
        $this->assertSame(['thumb', 'medium', 'large'], array_keys($media->variants));
        foreach ($media->variants as $path) {
            Storage::disk('public')->assertExists($path);
        }
        $this->assertSame(300, max(getimagesizefromstring(Storage::disk('public')->get($media->variants['thumb']))[0], 1));
    }

    public function test_small_image_is_not_upscaled(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/admin/media', ['file' => $this->fakeJpeg(200, 100)])->assertCreated()
            ->assertJsonPath('data.width', 200)->assertJsonPath('data.variants', []);
    }

    public function test_rejects_non_images_and_fake_extensions(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/admin/media', ['file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])->assertStatus(422);
        $php = tempnam(sys_get_temp_dir(), 'x');
        file_put_contents($php, '<?php echo 1;');
        $this->postJson('/api/admin/media', ['file' => new UploadedFile($php, 'shell.jpg', 'image/jpeg', null, true)])->assertStatus(422);
        $this->assertSame(0, Media::count());
    }

    public function test_multi_upload_search_and_update(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/admin/media', ['files' => [$this->fakeJpeg(500, 500, 'cambio.jpg'), $this->fakeJpeg(500, 500, 'riani.jpg')], 'folder' => 'general'])
            ->assertCreated()->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/media?q=cambio')->assertOk()->assertJsonPath('meta.total', 1);
        $id = Media::where('original_name', 'riani.jpg')->value('id');
        $this->putJson("/api/admin/media/$id", ['alt' => 'Riani look'])->assertOk()->assertJsonPath('data.alt', 'Riani look');
        $this->getJson('/api/admin/media?missing_alt=1')->assertJsonPath('meta.total', 1);
    }

    public function test_media_folders_endpoint_returns_all_folders_with_counts(): void
    {
        $this->actingAsAdmin();
        $this->postJson('/api/admin/media', ['file' => $this->fakeJpeg(), 'folder' => 'posts'])->assertCreated();

        $this->getJson('/api/admin/media/folders')
            ->assertOk()
            ->assertJsonPath('data.0.folder', 'pages')
            ->assertJsonPath('data.0.count', 0)
            ->assertJsonPath('data.1.folder', 'posts')
            ->assertJsonPath('data.1.count', 1);
    }

    public function test_public_storage_url_serves_media_without_a_storage_symlink(): void
    {
        Storage::disk('public')->put('uploads/general/test.webp', 'test image content');

        $this->get('/storage/uploads/general/test.webp')
            ->assertOk()
            ->assertStreamedContent('test image content');
    }

    public function test_public_media_urls_use_the_configured_media_base_url(): void
    {
        config(['media.base_url' => 'https://cdn.example.test/']);

        $this->assertSame(
            'https://cdn.example.test/storage/uploads/general/image.webp',
            Media::urlFor('public', 'uploads/general/image.webp')
        );
    }

    public function test_image_content_stores_paths_and_uses_the_current_media_base_url(): void
    {
        config(['media.base_url' => 'https://api.example.test']);
        $post = Post::create([
            'title' => 'Path-only image',
            'slug' => 'path-only-image',
            'content' => '<img src="http://localhost:8000/storage/uploads/posts/2024/03/image.webp">',
            'status' => 'draft',
        ]);

        $this->assertSame(
            '<img src="/storage/uploads/posts/2024/03/image.webp">',
            DB::table('posts')->where('id', $post->id)->value('content')
        );
        $this->assertSame(
            '<img src="https://api.example.test/storage/uploads/posts/2024/03/image.webp">',
            $post->fresh()->content
        );

        $page = Page::create([
            'title' => 'Image sections',
            'slug' => 'image-sections',
            'sections' => ['hero' => ['image' => 'http://localhost:8000/storage/uploads/pages/hero.webp']],
        ]);
        $this->assertSame(
            ['hero' => ['image' => '/storage/uploads/pages/hero.webp']],
            json_decode(DB::table('pages')->where('id', $page->id)->value('sections'), true)
        );
        $this->assertSame(
            ['hero' => ['image' => 'https://api.example.test/storage/uploads/pages/hero.webp']],
            $page->fresh()->sections
        );
    }

    public function test_data_migration_normalizes_existing_localhost_image_references(): void
    {
        $post = Post::create(['title' => 'Legacy image', 'slug' => 'legacy-image', 'status' => 'draft']);
        DB::table('posts')->where('id', $post->id)
            ->update(['content' => '<img src="http://localhost:8000/storage/uploads/posts/legacy.webp">']);
        $mediaId = DB::table('media')->insertGetId([
            'disk' => 'public',
            'folder' => 'posts',
            'path' => 'http://localhost:8000/storage/uploads/posts/legacy.webp',
            'filename' => 'legacy.webp',
            'mime_type' => 'image/webp',
            'extension' => 'webp',
            'variants' => json_encode(['thumb' => 'http://localhost:8000/storage/uploads/posts/legacy-thumb.webp']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('settings')->insert([
            'key' => 'legacy_image',
            'value' => json_encode(['image' => 'http://localhost:8000/storage/uploads/general/logo.webp']),
            'group' => 'general',
            'is_public' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_10_12_000500_normalize_localhost_media_urls.php');
        $migration->up();

        $this->assertSame(
            '<img src="/storage/uploads/posts/legacy.webp">',
            DB::table('posts')->where('id', $post->id)->value('content')
        );
        $this->assertSame('uploads/posts/legacy.webp', DB::table('media')->where('id', $mediaId)->value('path'));
        $this->assertSame(
            ['thumb' => 'uploads/posts/legacy-thumb.webp'],
            json_decode(DB::table('media')->where('id', $mediaId)->value('variants'), true)
        );
        $this->assertSame(
            ['image' => '/storage/uploads/general/logo.webp'],
            json_decode(DB::table('settings')->where('key', 'legacy_image')->value('value'), true)
        );
    }

    public function test_settings_store_image_paths_and_return_dynamic_image_urls(): void
    {
        config(['media.base_url' => 'https://api.example.test']);
        $settings = app(SettingsService::class);
        $settings->set('logo_url', 'http://localhost:8000/storage/uploads/general/logo.webp');

        $this->assertSame(
            '/storage/uploads/general/logo.webp',
            json_decode(DB::table('settings')->where('key', 'logo_url')->value('value'), true)
        );
        $this->assertSame(
            'https://api.example.test/storage/uploads/general/logo.webp',
            $settings->get('logo_url')
        );
    }

    public function test_in_use_image_is_protected_and_replace_keeps_links_working(): void
    {
        $this->actingAsAdmin();
        $id = $this->postJson('/api/admin/media', ['file' => $this->fakeJpeg(900, 600)])->json('data.id');
        $media = Media::find($id);
        $post = Post::create(['title' => 'X', 'slug' => 'x', 'status' => 'published', 'published_at' => now(),
            'content' => '<p><img src="'.$media->url.'"></p>', 'featured_media_id' => $id]);

        $this->deleteJson("/api/admin/media/$id")->assertStatus(409)->assertJsonPath('errors.usages.0.type', 'post');

        $oldPath = $media->path;
        $this->postJson("/api/admin/media/$id/replace", ['file' => $this->fakeJpeg(1200, 800)])->assertOk()->assertJsonPath('data.width', 1200);
        $media->refresh();
        $this->assertSame($oldPath, $media->path); // same file name → every link keeps working
        $this->assertStringContainsString($media->url, $post->fresh()->content);

        $this->deleteJson("/api/admin/media/$id?force=1")->assertOk();
        Storage::disk('public')->assertMissing($oldPath);
    }
}
