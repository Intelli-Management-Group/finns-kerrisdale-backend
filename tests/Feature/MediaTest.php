<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
