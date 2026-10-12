<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PostRequest;
use App\Http\Resources\PostListResource;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Services\PostService;
use App\Support\ApiResponse;
use App\Support\ContentCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function __construct(private PostService $posts) {}

    public function index(Request $request): JsonResponse
    {
        $query = Post::query()->with(['categories:id,name,slug', 'author:id,name']);

        if ($request->query('trashed') === 'only') {
            $query->onlyTrashed();
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($cat = $request->query('category_id')) {
            $query->whereHas('categories', fn ($q) => $q->where('categories.id', $cat));
        }
        if ($tag = $request->query('tag_id')) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tag));
        }
        if ($author = $request->query('author_id')) {
            $query->where('author_id', $author);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('published_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('published_at', '<=', $to);
        }

        $page = $this->paginateQuery($request, $query, ['title', 'slug', 'content'], ['title', 'status', 'published_at', 'updated_at', 'id'], '-published_at');

        return ApiResponse::success(PostListResource::collection($page), 'Posts retrieved successfully');
    }

    public function store(PostRequest $request): JsonResponse
    {
        $post = $this->posts->save(new Post, $request->validated(), $request->user());

        return ApiResponse::created(new PostResource($post), 'Post created successfully');
    }

    public function show(Post $post): JsonResponse
    {
        $post->load(['categories', 'tags', 'images', 'featuredMedia', 'ogMedia', 'author']);

        return ApiResponse::success(new PostResource($post), 'Post retrieved successfully');
    }

    public function update(PostRequest $request, Post $post): JsonResponse
    {
        $post = $this->posts->save($post, $request->validated(), $request->user());

        return ApiResponse::success(new PostResource($post), 'Post updated successfully');
    }

    public function status(Request $request, Post $post): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(Post::STATUSES)]]);
        $post = $this->posts->save($post, $data, $request->user());

        return ApiResponse::success(new PostResource($post), $post->status === 'published' ? 'Post published' : 'Post unpublished');
    }

    public function destroy(Post $post): JsonResponse
    {
        $post->delete();
        ContentCache::bust();

        return ApiResponse::success(null, 'Post moved to trash');
    }

    public function restore(int $id): JsonResponse
    {
        $post = Post::onlyTrashed()->findOrFail($id);
        $post->restore();
        ContentCache::bust();

        return ApiResponse::success(new PostResource($post), 'Post restored');
    }

    public function forceDelete(int $id): JsonResponse
    {
        Post::onlyTrashed()->findOrFail($id)->forceDelete();

        return ApiResponse::success(null, 'Post permanently deleted');
    }

    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:200'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['publish', 'unpublish', 'delete'])],
        ]);
        $query = Post::query()->whereIn('id', $data['ids']);
        $count = match ($data['action']) {
            'publish' => $query->get()->each(fn (Post $p) => $this->posts->save($p, ['status' => 'published']))->count(),
            'unpublish' => $query->update(['status' => 'draft']),
            'delete' => $query->get()->each->delete()->count(),
        };
        ContentCache::bust();

        return ApiResponse::success(['affected' => $count], 'Bulk action completed');
    }
}
