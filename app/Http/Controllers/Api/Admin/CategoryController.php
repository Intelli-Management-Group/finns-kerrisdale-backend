<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TaxonomyRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\ApiResponse;
use App\Support\ContentCache;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Category::query()->withCount('posts');
        if ($request->boolean('all')) {
            return ApiResponse::success(CategoryResource::collection($query->orderBy('sort_order')->orderBy('name')->get()), 'Categories retrieved successfully');
        }
        $page = $this->paginateQuery($request, $query, ['name', 'slug'], ['name', 'slug', 'posts_count', 'sort_order', 'id'], 'name');

        return ApiResponse::success(CategoryResource::collection($page), 'Categories retrieved successfully');
    }

    public function store(TaxonomyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = Slug::unique(Category::class, $data['slug'] ?? $data['name']);
        $category = Category::create($data);
        ContentCache::bust();

        return ApiResponse::created(new CategoryResource($category->loadCount('posts')), 'Category created successfully');
    }

    public function show(Category $category): JsonResponse
    {
        return ApiResponse::success(new CategoryResource($category->loadCount('posts')), 'Category retrieved successfully');
    }

    public function update(TaxonomyRequest $request, Category $category): JsonResponse
    {
        $data = $request->validated();
        if (! empty($data['slug'])) {
            $data['slug'] = Slug::unique(Category::class, $data['slug'], $category->id);
        } else {
            unset($data['slug']);
        }
        if (($data['parent_id'] ?? null) === $category->id) {
            return ApiResponse::error('A category cannot be its own parent.', 422);
        }
        $category->update($data);
        ContentCache::bust();

        return ApiResponse::success(new CategoryResource($category->loadCount('posts')), 'Category updated successfully');
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();
        ContentCache::bust();

        return ApiResponse::success(null, 'Category deleted successfully');
    }
}
