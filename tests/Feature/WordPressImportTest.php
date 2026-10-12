<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Subscriber;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Builds a small WordPress database (same table layout as the real one) and imports it.
 */
class WordPressImportTest extends TestCase
{
    use RefreshDatabase;

    private string $uploads;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['database.connections.wordpress' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        $this->buildWordPress();
    }

    private function buildWordPress(): void
    {
        $s = Schema::connection('wordpress');
        $s->create('wp_posts', function (Blueprint $t) {
            $t->bigIncrements('ID');
            $t->unsignedBigInteger('post_author')->default(1);
            $t->dateTime('post_date')->nullable();
            $t->longText('post_content')->default('');
            $t->text('post_title')->default('');
            $t->text('post_excerpt')->default('');
            $t->string('post_status')->default('publish');
            $t->string('post_name')->default('');
            $t->unsignedBigInteger('post_parent')->default(0);
            $t->string('guid')->default('');
            $t->integer('menu_order')->default(0);
            $t->string('post_type')->default('post');
            $t->string('post_mime_type')->default('');
        });
        $s->create('wp_postmeta', function (Blueprint $t) {
            $t->bigIncrements('meta_id');
            $t->unsignedBigInteger('post_id');
            $t->string('meta_key')->nullable();
            $t->longText('meta_value')->nullable();
        });
        $s->create('wp_terms', function (Blueprint $t) {
            $t->bigIncrements('term_id');
            $t->string('name');
            $t->string('slug');
        });
        $s->create('wp_term_taxonomy', function (Blueprint $t) {
            $t->bigIncrements('term_taxonomy_id');
            $t->unsignedBigInteger('term_id');
            $t->string('taxonomy');
            $t->longText('description')->default('');
            $t->unsignedBigInteger('parent')->default(0);
        });
        $s->create('wp_term_relationships', function (Blueprint $t) {
            $t->unsignedBigInteger('object_id');
            $t->unsignedBigInteger('term_taxonomy_id');
        });
        $s->create('wp_users', function (Blueprint $t) {
            $t->bigIncrements('ID');
            $t->string('user_login');
            $t->string('user_email');
            $t->string('display_name');
        });
        $s->create('wp_options', function (Blueprint $t) {
            $t->bigIncrements('option_id');
            $t->string('option_name');
            $t->longText('option_value');
        });
        $s->create('wp_comments', function (Blueprint $t) {
            $t->bigIncrements('comment_ID');
            $t->string('comment_approved')->default('1');
        });
        $s->create('wp_aioseo_posts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('post_id');
            $t->text('title')->nullable();
            $t->text('description')->nullable();
        });

        $wp = DB::connection('wordpress');
        $wp->table('wp_users')->insert([['ID' => 1, 'user_login' => 'admin', 'user_email' => 'old@finns.test', 'display_name' => 'admin'], ['ID' => 2, 'user_login' => 'finnsadmin', 'user_email' => 'fkl@finns.test', 'display_name' => 'finnsadmin']]);
        $wp->table('wp_options')->insert([['option_name' => 'page_on_front', 'option_value' => '6'], ['option_name' => 'page_for_posts', 'option_value' => '10']]);
        $wp->table('wp_terms')->insert([['term_id' => 1, 'name' => 'Uncategorized', 'slug' => 'uncategorized'], ['term_id' => 5, 'name' => 'Riani', 'slug' => 'riani'], ['term_id' => 9, 'name' => 'Navy &amp; White', 'slug' => 'navy-white']]);
        $wp->table('wp_term_taxonomy')->insert([['term_taxonomy_id' => 1, 'term_id' => 1, 'taxonomy' => 'category'], ['term_taxonomy_id' => 5, 'term_id' => 5, 'taxonomy' => 'category'], ['term_taxonomy_id' => 9, 'term_id' => 9, 'taxonomy' => 'post_tag']]);

        // uploads folder with a real image
        $this->uploads = sys_get_temp_dir().'/wpup-'.uniqid();
        mkdir($this->uploads.'/2021/04', 0777, true);
        $img = imagecreatetruecolor(838, 1280);
        imagefill($img, 0, 0, imagecolorallocate($img, 30, 40, 90));
        imagejpeg($img, $this->uploads.'/2021/04/thumbnail_IMG_6310.jpg', 95);

        $rows = [
            // pages
            ['ID' => 6, 'post_title' => 'Home', 'post_name' => 'home', 'post_type' => 'page', 'post_content' => '[vc_row][vc_column]<p>WP home</p>[/vc_column][/vc_row]', 'post_date' => '2015-02-05 04:21:27'],
            ['ID' => 2, 'post_title' => 'Sample Page', 'post_name' => 'sample-page', 'post_type' => 'page', 'post_content' => 'x', 'post_date' => '2015-02-05 03:56:10'],
            ['ID' => 90, 'post_title' => 'Gift Cards', 'post_name' => 'gift-cards', 'post_type' => 'page', 'post_content' => "[vc_row]Buy a gift card\n\nIn store only[/vc_row]", 'post_date' => '2020-01-01 10:00:00'],
            // attachment
            ['ID' => 394, 'post_title' => 'thumbnail_IMG_6310', 'post_name' => 'thumbnail_img_6310', 'post_type' => 'attachment', 'post_mime_type' => 'image/jpeg', 'post_parent' => 393, 'guid' => 'http://finnskerrisdaleladies.com/wp-content/uploads/2021/04/thumbnail_IMG_6310.jpg', 'post_date' => '2021-04-09 01:39:00', 'post_content' => ''],
            // posts: one existing in the seeder (classic editor, no <p>), one new, one draft
            ['ID' => 393, 'post_author' => 2, 'post_title' => 'RIANI S&#8217;21', 'post_name' => 'riani-s21', 'post_type' => 'post', 'post_date' => '2021-04-09 01:39:42',
                'post_content' => "RIANI IS A BRAND CLOSE TO MY HEART.\n\n<a href=\"http://www.finnskerrisdaleladies.com/wp-content/uploads/2021/04/thumbnail_IMG_6310.jpg\"><img class=\"size-medium wp-image-394\" src=\"http://finnskerrisdaleladies.com/wp-content/uploads/2021/04/thumbnail_IMG_6310-196x300.jpg\" alt=\"dress\" width=\"196\" height=\"300\" srcset=\"x 1w\" /></a>\nON THE CHANCE YOU DO HAVE AN OCCASION"],
            ['ID' => 700, 'post_author' => 2, 'post_title' => 'Fall 2026 Preview', 'post_name' => 'fall-2026-preview', 'post_type' => 'post', 'post_date' => '2026-09-01 09:00:00',
                'post_content' => '<!-- wp:paragraph --><p>New season <script>alert(1)</script></p><!-- /wp:paragraph -->'],
            ['ID' => 701, 'post_author' => 2, 'post_title' => 'Draft idea', 'post_name' => '', 'post_type' => 'post', 'post_status' => 'draft', 'post_date' => '2026-09-02 09:00:00', 'post_content' => 'tbd'],
            // subscriber stored by Easy Registration Forms
            ['ID' => 800, 'post_title' => 'Submission', 'post_name' => 'sub-800', 'post_type' => 'erforms_submission', 'post_date' => '2023-05-01 10:00:00', 'post_content' => ''],
        ];
        foreach ($rows as $row) {
            $wp->table('wp_posts')->insert($row);
        }
        $wp->table('wp_postmeta')->insert([
            ['post_id' => 394, 'meta_key' => '_wp_attached_file', 'meta_value' => '2021/04/thumbnail_IMG_6310.jpg'],
            ['post_id' => 394, 'meta_key' => '_wp_attachment_image_alt', 'meta_value' => 'Riani biscuit dress'],
            ['post_id' => 700, 'meta_key' => '_thumbnail_id', 'meta_value' => '394'],
            ['post_id' => 800, 'meta_key' => 'erf_submission', 'meta_value' => serialize(['fields' => [['name' => 'Email', 'value' => 'Loyal.Customer@Example.com']]])],
        ]);
        $wp->table('wp_term_relationships')->insert([['object_id' => 393, 'term_taxonomy_id' => 5], ['object_id' => 393, 'term_taxonomy_id' => 9], ['object_id' => 700, 'term_taxonomy_id' => 1]]);
        $wp->table('wp_aioseo_posts')->insert([['post_id' => 700, 'title' => '#post_title Fall Preview', 'description' => 'First look at fall 2026.']]);
        $wp->table('wp_comments')->insert([['comment_approved' => '1'], ['comment_approved' => 'spam']]);
    }

    public function test_full_import_is_correct_and_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $seededHome = Page::where('template', 'home')->value('content');

        $this->artisan('wp:import', ['--uploads' => $this->uploads])->assertSuccessful();

        // media: compressed WebP in the right folder, alt text kept
        $media = Media::where('wp_id', 394)->firstOrFail();
        $this->assertSame('image/webp', $media->mime_type);
        $this->assertStringStartsWith('uploads/posts/2021/04/', $media->path);
        $this->assertSame('Riani biscuit dress', $media->alt);
        Storage::disk('public')->assertExists($media->path);

        // existing seeded post adopted (not duplicated), content converted and image links rewritten
        $this->assertSame(1, Post::where('slug', 'riani-s21')->count());
        $riani = Post::where('slug', 'riani-s21')->first();
        $this->assertSame(393, $riani->wp_id);
        $this->assertSame('RIANI S’21', $riani->title);
        $this->assertStringContainsString('<p>RIANI IS A BRAND CLOSE TO MY HEART.</p>', $riani->content);
        $this->assertStringNotContainsString('wp-content/uploads', $riani->content);
        $this->assertStringContainsString($media->variantUrls()['thumb'], $riani->content);   // -196x300 → thumb
        $this->assertStringContainsString('href="'.$media->url.'"', $riani->content);          // full-size link → original
        $this->assertStringNotContainsString('srcset', $riani->content);
        $this->assertEqualsCanonicalizing(['riani'], $riani->categories->pluck('slug')->all());
        $this->assertSame(['Navy & White'], $riani->tags->pluck('name')->all());

        // new post: sanitized, featured image, AIOSEO description, unresolved variable stripped from title
        $fall = Post::where('slug', 'fall-2026-preview')->firstOrFail();
        $this->assertSame('<p>New season </p>', $fall->content);
        $this->assertSame($media->id, $fall->featured_media_id);
        $this->assertSame('First look at fall 2026.', $fall->seo_description);
        $this->assertSame('Fall Preview', $fall->seo_title);
        $this->assertSame('published', $fall->status);

        // draft without slug gets one and stays draft
        $draft = Post::where('wp_id', 701)->firstOrFail();
        $this->assertSame('draft', $draft->status);
        $this->assertSame('draft-idea', $draft->slug);

        // pages: curated home layout kept, new page created with paragraphs, sample page skipped
        $this->assertSame($seededHome, Page::where('template', 'home')->value('content'));
        $this->assertSame(6, Page::where('template', 'home')->value('wp_id'));
        $gift = Page::where('slug', 'gift-cards')->firstOrFail();
        $this->assertSame("<p>Buy a gift card</p>\n<p>In store only</p>", $gift->content);
        $this->assertNull(Page::where('slug', 'sample-page')->first());

        $this->assertTrue(Category::where('slug', 'riani')->exists());
        $this->assertTrue(Subscriber::where('email', 'loyal.customer@example.com')->exists());

        // second run changes nothing
        $counts = [Post::count(), Page::count(), Media::count(), Subscriber::count()];
        $this->artisan('wp:import', ['--uploads' => $this->uploads])->assertSuccessful();
        $this->assertSame($counts, [Post::count(), Page::count(), Media::count(), Subscriber::count()]);
    }

    public function test_dry_run_saves_nothing(): void
    {
        $this->artisan('wp:import', ['--uploads' => $this->uploads, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, Post::count());
        $this->assertSame(0, Media::count());
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_wrong_database_fails_clearly(): void
    {
        $this->artisan('wp:import', ['--skip-media' => true, '--prefix' => 'nope_'])
            ->expectsOutputToContain('WordPress table nope_posts not found')
            ->assertFailed();
    }
}
