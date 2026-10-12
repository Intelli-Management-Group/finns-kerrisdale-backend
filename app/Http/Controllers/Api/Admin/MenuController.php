<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuItemRequest;
use App\Http\Resources\SimpleResource;
use App\Models\MenuItem;
use App\Support\ApiResponse;
use App\Support\ContentCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MenuController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = MenuItem::query()->where('location', $request->query('location', 'header'))->orderBy('sort_order')->get();

        return ApiResponse::success(SimpleResource::collection($items), 'Menu retrieved successfully');
    }

    /** Replace the whole menu in one go (keeps ordering simple for the drag-and-drop UI). */
    public function update(MenuItemRequest $request): JsonResponse
    {
        $location = $request->input('location', 'header');
        DB::transaction(function () use ($request, $location) {
            MenuItem::where('location', $location)->delete();
            foreach ($request->validated()['items'] as $i => $item) {
                MenuItem::create($item + ['location' => $location, 'sort_order' => $i]);
            }
        });
        ContentCache::bust();
        $items = MenuItem::query()->where('location', $location)->orderBy('sort_order')->get();

        return ApiResponse::success(SimpleResource::collection($items), 'Menu saved successfully');
    }
}
