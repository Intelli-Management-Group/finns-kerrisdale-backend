<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Support\ApiResponse;
use App\Support\ContentCache;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Brand::query()->with(['logo', 'brandThumbnailMedia']);
        if ($request->boolean('all')) {
            return ApiResponse::success(BrandResource::collection($query->orderBy('sort_order')->get()), 'Brands retrieved successfully');
        }

        return ApiResponse::success(BrandResource::collection($this->paginateQuery($request, $query, ['name', 'slug'], ['name', 'sort_order', 'id'], 'sort_order')), 'Brands retrieved successfully');
    }

    public function store(BrandRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = Slug::unique(Brand::class, $data['slug'] ?? $data['name']);
        $data['sort_order'] ??= (int) Brand::max('sort_order') + 1;
        $brand = Brand::create($data);
        ContentCache::bust();

        return ApiResponse::created(new BrandResource($brand->load(['logo', 'brandThumbnailMedia'])), 'Brand created successfully');
    }

    public function show(Brand $brand): JsonResponse
    {
        return ApiResponse::success(new BrandResource($brand->load(['logo', 'brandThumbnailMedia'])), 'Brand retrieved successfully');
    }

    public function update(BrandRequest $request, Brand $brand): JsonResponse
    {
        $data = $request->validated();
        if (! empty($data['slug'])) {
            $data['slug'] = Slug::unique(Brand::class, $data['slug'], $brand->id);
        } else {
            unset($data['slug']);
        }
        $brand->update($data);
        ContentCache::bust();

        return ApiResponse::success(new BrandResource($brand->load(['logo', 'brandThumbnailMedia'])), 'Brand updated successfully');
    }

    public function reorder(Request $request): JsonResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer', 'exists:brands,id']])['ids'];
        foreach ($ids as $i => $id) {
            Brand::whereKey($id)->update(['sort_order' => $i]);
        }
        ContentCache::bust();

        return ApiResponse::success(null, 'Brand order saved');
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $brand->delete();
        ContentCache::bust();

        return ApiResponse::success(null, 'Brand deleted successfully');
    }
}
