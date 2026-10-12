<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\BrandResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\GalleryResource;
use App\Http\Resources\PageResource;
use App\Http\Resources\PostListResource;
use App\Http\Resources\PostResource;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Gallery;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Post;
use App\Models\Redirect;
use App\Services\SettingsService;
use App\Support\ApiResponse;
use App\Support\ContentCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only public API used by the React website. Responses are cached and bust on any admin write.
 */
class ContentController extends Controller
{
    public function __construct(private SettingsService $settings) {}

    /** Site-wide data: settings, header menu, categories. */
    public function site(Request $request): JsonResponse
    {
        $data = ContentCache::remember('site', fn () => [
            'settings' => $this->settings->all(publicOnly: true),
            'menu' => MenuItem::where('location', 'header')->where('is_active', true)->orderBy('sort_order')
                ->get(['label', 'url', 'open_in_new_tab'])->toArray(),
            'categories' => ApiResponse::plain(CategoryResource::collection(Category::withCount(['posts' => fn ($q) => $q->published()])->orderBy('name')->get())),
        ]);

        return ApiResponse::success($data, 'Site data retrieved successfully');
    }

    /** Tell the SPA what lives at /{slug}/ (WordPress kept pages and posts in one URL space). */
    public function resolve(Request $request, string $slug): JsonResponse
    {
        $slug = strtolower(trim($slug, '/'));

        if ($page = Page::published()->where('slug', $slug)->first()) {
            return ApiResponse::success(['type' => 'page', 'item' => $this->pagePayload($request, $page)], 'Resolved');
        }
        if ($post = Post::published()->where('slug', $slug)->first()) {
            return ApiResponse::success(['type' => 'post', 'item' => $this->postPayload($request, $post)], 'Resolved');
        }
        $redirect = Redirect::where('from_path', Redirect::normalizePath($slug))->where('is_active', true)->first();
        if ($redirect) {
            return ApiResponse::success(['type' => 'redirect', 'to' => $redirect->to_path, 'status' => $redirect->status_code], 'Resolved');
        }

        return ApiResponse::error('Page not found', 404);
    }

    public function pages(Request $request): JsonResponse
    {
        $pages = ContentCache::remember('pages', fn () => ApiResponse::plain(PageResource::collection(Page::published()->orderBy('menu_order')->get())));

        return ApiResponse::success($pages, 'Pages retrieved successfully');
    }

    public function page(Request $request, string $slug): JsonResponse
    {
        $page = $slug === 'home'
            ? Page::published()->where('template', 'home')->first()
            : Page::published()->where('slug', $slug)->first();
        if (! $page) {
            return ApiResponse::error('Page not found', 404);
        }

        return ApiResponse::success($this->pagePayload($request, $page), 'Page retrieved successfully');
    }

    public function posts(Request $request): JsonResponse
    {
        $perPage = max(1, min(50, (int) $request->query('per_page', (int) $this->settings->get('posts_per_page', 10))));
        $key = 'posts:'.md5(json_encode($request->only(['page', 'per_page', 'category', 'tag', 'q', 'with_content', 'year'])).$perPage);

        $payload = ContentCache::remember($key, function () use ($request, $perPage) {
            $query = Post::published()->with('categories:id,name,slug')->orderByDesc('published_at')->orderByDesc('id');
            if ($cat = $request->query('category')) {
                $query->whereHas('categories', fn ($q) => $q->where('slug', $cat));
            }
            if ($tag = $request->query('tag')) {
                $query->whereHas('tags', fn ($q) => $q->where('slug', $tag));
            }
            if ($year = $request->query('year')) {
                $query->whereYear('published_at', (int) $year);
            }
            if (($q = trim((string) $request->query('q'))) !== '') {
                $query->where(fn ($w) => $w->where('title', 'like', "%$q%")->orWhere('content', 'like', "%$q%"));
            }
            $page = $query->paginate($perPage);

            return [
                'data' => ApiResponse::plain(PostListResource::collection($page->getCollection())),
                'meta' => ApiResponse::paginationMeta($page),
            ];
        });

        return ApiResponse::success($payload['data'], 'Posts retrieved successfully', 200, $payload['meta']);
    }

    public function post(Request $request, string $slug): JsonResponse
    {
        $post = Post::published()->where('slug', $slug)->first();
        if (! $post) {
            return ApiResponse::error('Post not found', 404);
        }

        return ApiResponse::success($this->postPayload($request, $post), 'Post retrieved successfully');
    }

    public function categories(Request $request): JsonResponse
    {
        $data = ContentCache::remember('categories', fn () => ApiResponse::plain(CategoryResource::collection(
            Category::withCount(['posts' => fn ($q) => $q->published()])->orderBy('sort_order')->orderBy('name')->get()
        )));

        return ApiResponse::success($data, 'Categories retrieved successfully');
    }

    public function brands(Request $request): JsonResponse
    {
        $data = ContentCache::remember('brands', fn () => ApiResponse::plain(BrandResource::collection(
            Brand::with('logo')->where('is_active', true)->orderBy('sort_order')->get()
        )));

        return ApiResponse::success($data, 'Brands retrieved successfully');
    }

    public function galleries(Request $request): JsonResponse
    {
        $data = ContentCache::remember('galleries', fn () => ApiResponse::plain(GalleryResource::collection(
            Gallery::published()->with('cover', 'media')->withCount('media')->orderBy('sort_order')->get()
        )));

        return ApiResponse::success($data, 'Galleries retrieved successfully');
    }

    public function gallery(Request $request, string $slug): JsonResponse
    {
        $gallery = Gallery::published()->where('slug', $slug)->with(['cover', 'media'])->withCount('media')->first();
        if (! $gallery) {
            return ApiResponse::error('Gallery not found', 404);
        }

        return ApiResponse::success(ApiResponse::plain(new GalleryResource($gallery)), 'Gallery retrieved successfully');
    }

    private function pagePayload(Request $request, Page $page): array
    {
        return ContentCache::remember('page:'.$page->id, function () use ($request, $page) {
            $data = ApiResponse::plain(new PageResource($page->load(['featuredMedia', 'ogMedia'])));
            if ($page->template === 'designer') {
                $data['brands'] = ApiResponse::plain(BrandResource::collection(Brand::with('logo')->where('is_active', true)->orderBy('sort_order')->get()));
            }
            if ($page->template === 'home') {
                $data['latest_posts'] = ApiResponse::plain(PostListResource::collection(Post::published()->latest('published_at')->limit(3)->get()));
            }

            return $data;
        });
    }

    private function postPayload(Request $request, Post $post): array
    {
        return ContentCache::remember('post:'.$post->id, function () use ($request, $post) {
            $post->load(['categories', 'tags', 'images', 'featuredMedia', 'ogMedia', 'author']);
            $data = ApiResponse::plain(new PostResource($post));

            // WordPress "Previous post" = older, "Next post" = newer
            $prev = Post::published()->where(fn ($q) => $q->where('published_at', '<', $post->published_at)
                ->orWhere(fn ($w) => $w->where('published_at', $post->published_at)->where('id', '<', $post->id)))
                ->orderByDesc('published_at')->orderByDesc('id')->first(['id', 'title', 'slug']);
            $next = Post::published()->where(fn ($q) => $q->where('published_at', '>', $post->published_at)
                ->orWhere(fn ($w) => $w->where('published_at', $post->published_at)->where('id', '>', $post->id)))
                ->orderBy('published_at')->orderBy('id')->first(['id', 'title', 'slug']);
            $data['previous'] = $prev ? ['title' => $prev->title, 'slug' => $prev->slug, 'path' => $prev->path()] : null;
            $data['next'] = $next ? ['title' => $next->title, 'slug' => $next->slug, 'path' => $next->path()] : null;

            return $data;
        });
    }
}
