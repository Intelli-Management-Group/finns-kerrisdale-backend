<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PageRequest;
use App\Http\Resources\PageResource;
use App\Models\Page;
use App\Services\PageService;
use App\Support\ApiResponse;
use App\Support\ContentCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    public function __construct(private PageService $pages) {}

    public function index(Request $request): JsonResponse
    {
        $query = Page::query();
        if ($request->query('trashed') === 'only') {
            $query->onlyTrashed();
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($template = $request->query('template')) {
            $query->where('template', $template);
        }
        $page = $this->paginateQuery($request, $query, ['title', 'slug'], ['title', 'status', 'menu_order', 'updated_at', 'id'], 'menu_order');

        return ApiResponse::success(PageResource::collection($page), 'Pages retrieved successfully');
    }

    public function store(PageRequest $request): JsonResponse
    {
        return ApiResponse::created(new PageResource($this->pages->save(new Page, $request->validated(), $request->user())), 'Page created successfully');
    }

    public function show(Page $page): JsonResponse
    {
        return ApiResponse::success(new PageResource($page->load(['featuredMedia', 'ogMedia'])), 'Page retrieved successfully');
    }

    public function update(PageRequest $request, Page $page): JsonResponse
    {
        return ApiResponse::success(new PageResource($this->pages->save($page, $request->validated(), $request->user())), 'Page updated successfully');
    }

    public function status(Request $request, Page $page): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(Page::STATUSES)]]);
        $page = $this->pages->save($page, $data);

        return ApiResponse::success(new PageResource($page), $page->status === 'published' ? 'Page published' : 'Page unpublished');
    }

    public function destroy(Page $page): JsonResponse
    {
        if ($page->template === 'home') {
            return ApiResponse::error('The home page cannot be deleted.', 422);
        }
        $page->delete();
        ContentCache::bust();

        return ApiResponse::success(null, 'Page moved to trash');
    }

    public function restore(int $id): JsonResponse
    {
        $page = Page::onlyTrashed()->findOrFail($id);
        $page->restore();
        ContentCache::bust();

        return ApiResponse::success(new PageResource($page), 'Page restored');
    }

    public function forceDelete(int $id): JsonResponse
    {
        Page::onlyTrashed()->findOrFail($id)->forceDelete();

        return ApiResponse::success(null, 'Page permanently deleted');
    }
}
