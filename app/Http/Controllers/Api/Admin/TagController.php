<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TaxonomyRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Support\ApiResponse;
use App\Support\ContentCache;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Tag::query()->withCount('posts');
        if ($request->boolean('all')) {
            return ApiResponse::success(TagResource::collection($query->orderBy('name')->get()), 'Tags retrieved successfully');
        }

        return ApiResponse::success(TagResource::collection($this->paginateQuery($request, $query, ['name', 'slug'], ['name', 'posts_count', 'id'], 'name')), 'Tags retrieved successfully');
    }

    public function store(TaxonomyRequest $request): JsonResponse
    {
        $data = $request->safe()->only(['name', 'slug']);
        $data['slug'] = Slug::unique(Tag::class, $data['slug'] ?? $data['name']);

        return ApiResponse::created(new TagResource(Tag::create($data)), 'Tag created successfully');
    }

    public function update(TaxonomyRequest $request, Tag $tag): JsonResponse
    {
        $data = $request->safe()->only(['name', 'slug']);
        if (! empty($data['slug'])) {
            $data['slug'] = Slug::unique(Tag::class, $data['slug'], $tag->id);
        }
        $tag->update($data);
        ContentCache::bust();

        return ApiResponse::success(new TagResource($tag), 'Tag updated successfully');
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $tag->delete();
        ContentCache::bust();

        return ApiResponse::success(null, 'Tag deleted successfully');
    }
}
