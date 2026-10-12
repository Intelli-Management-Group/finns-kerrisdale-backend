<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GalleryRequest;
use App\Http\Resources\GalleryResource;
use App\Models\Gallery;
use App\Support\ApiResponse;
use App\Support\ContentCache;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class GalleryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Gallery::query()->with('cover')->withCount('media');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return ApiResponse::success(GalleryResource::collection($this->paginateQuery($request, $query, ['title', 'slug'], ['title', 'status', 'sort_order', 'id'], 'sort_order')), 'Galleries retrieved successfully');
    }

    public function store(GalleryRequest $request): JsonResponse
    {
        return ApiResponse::created(new GalleryResource($this->save(new Gallery, $request->validated())), 'Gallery created successfully');
    }

    public function show(Gallery $gallery): JsonResponse
    {
        return ApiResponse::success(new GalleryResource($gallery->load(['cover', 'media'])->loadCount('media')), 'Gallery retrieved successfully');
    }

    public function update(GalleryRequest $request, Gallery $gallery): JsonResponse
    {
        return ApiResponse::success(new GalleryResource($this->save($gallery, $request->validated())), 'Gallery updated successfully');
    }

    public function destroy(Gallery $gallery): JsonResponse
    {
        $gallery->delete();
        ContentCache::bust();

        return ApiResponse::success(null, 'Gallery deleted successfully');
    }

    private function save(Gallery $gallery, array $data): Gallery
    {
        return DB::transaction(function () use ($gallery, $data) {
            $gallery->fill(Arr::except($data, ['slug', 'photos']));
            if (! empty($data['slug'])) {
                $gallery->slug = Slug::unique(Gallery::class, $data['slug'], $gallery->id);
            } elseif (! $gallery->slug) {
                $gallery->slug = Slug::unique(Gallery::class, $gallery->title);
            }
            $gallery->save();

            if (array_key_exists('photos', $data)) {
                $sync = [];
                foreach (array_values($data['photos']) as $i => $photo) {
                    $sync[$photo['media_id']] = ['caption' => $photo['caption'] ?? null, 'sort_order' => $i];
                }
                $gallery->media()->sync($sync);
            }
            ContentCache::bust();

            return $gallery->load(['cover', 'media'])->loadCount('media');
        });
    }
}
