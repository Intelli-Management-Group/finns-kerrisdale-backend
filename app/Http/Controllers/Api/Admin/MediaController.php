<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MediaReplaceRequest;
use App\Http\Requests\Admin\MediaUpdateRequest;
use App\Http\Requests\Admin\MediaUploadRequest;
use App\Http\Resources\MediaResource;
use App\Models\Media;
use App\Services\MediaService;
use App\Support\ApiResponse;
use App\Support\ContentCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class MediaController extends Controller
{
    public function __construct(private MediaService $media) {}

    public function index(Request $request): JsonResponse
    {
        $query = Media::query()->with('uploader:id,name');
        if ($folder = $request->query('folder')) {
            $query->where('folder', $folder);
        }
        if ($type = $request->query('type')) {
            $query->where('mime_type', 'like', $type.'%');
        }
        if ($request->boolean('missing_alt')) {
            $query->where(fn ($q) => $q->whereNull('alt')->orWhere('alt', ''));
        }
        $page = $this->paginateQuery($request, $query, ['original_name', 'filename', 'alt', 'title', 'caption'], ['created_at', 'size_bytes', 'original_name', 'width', 'id'], '-id');

        return ApiResponse::success(MediaResource::collection($page), 'Media retrieved successfully');
    }

    public function store(MediaUploadRequest $request): JsonResponse
    {
        $files = $request->file('files') ?? [$request->file('file')];
        $folder = $request->input('folder', 'general');
        $created = [];
        $errors = [];

        foreach ($files as $i => $file) {
            try {
                $created[] = $this->media->upload($file, $folder, $request->user()->id, array_filter(['alt' => $request->input('alt')]));
            } catch (RuntimeException $e) {
                $errors["files.$i"] = [$file->getClientOriginalName().': '.$e->getMessage()];
            }
        }

        if (! $created) {
            return ApiResponse::error('Upload failed', 422, $errors);
        }

        $data = MediaResource::collection(collect($created))->resolve($request);

        return ApiResponse::success($request->hasFile('file') ? $data[0] : $data, count($created).' file(s) uploaded', 201, $errors ? ['errors' => $errors] : []);
    }

    public function show(Media $media): JsonResponse
    {
        $resource = (new MediaResource($media->load('uploader')))->resolve(request());
        $resource['usages'] = $media->usages();

        return ApiResponse::success($resource, 'Media retrieved successfully');
    }

    public function update(MediaUpdateRequest $request, Media $media): JsonResponse
    {
        $media->update($request->validated());
        ContentCache::bust();

        return ApiResponse::success(new MediaResource($media), 'Media updated successfully');
    }

    public function replace(MediaReplaceRequest $request, Media $media): JsonResponse
    {
        try {
            $media = $this->media->replace($media, $request->file('file'));
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }
        ContentCache::bust();

        return ApiResponse::success(new MediaResource($media), 'Image replaced successfully');
    }

    public function destroy(Request $request, Media $media): JsonResponse
    {
        $usages = $media->usages();
        if ($usages && ! $request->boolean('force')) {
            return ApiResponse::error('This image is still in use. Remove it from these items first, or delete with force.', 409, ['usages' => $usages]);
        }
        $this->media->delete($media, true);
        ContentCache::bust();

        return ApiResponse::success(null, 'Media deleted successfully');
    }
}
