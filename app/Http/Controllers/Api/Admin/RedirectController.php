<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RedirectRequest;
use App\Http\Resources\SimpleResource;
use App\Models\Redirect;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RedirectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success(SimpleResource::collection($this->paginateQuery($request, Redirect::query(), ['from_path', 'to_path'], ['from_path', 'hits', 'status_code', 'id'], 'from_path')), 'Redirects retrieved successfully');
    }

    public function store(RedirectRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['from_path'] = Redirect::normalizePath($data['from_path']);
        if (Redirect::where('from_path', $data['from_path'])->exists()) {
            return ApiResponse::error('Validation failed', 422, ['from_path' => ['A redirect for this path already exists.']]);
        }
        $redirect = Redirect::create($data);
        Cache::forget('redirects.map');

        return ApiResponse::created(new SimpleResource($redirect), 'Redirect created successfully');
    }

    public function update(RedirectRequest $request, Redirect $redirect): JsonResponse
    {
        $data = $request->validated();
        if (isset($data['from_path'])) {
            $data['from_path'] = Redirect::normalizePath($data['from_path']);
            if (Redirect::where('from_path', $data['from_path'])->whereKeyNot($redirect->id)->exists()) {
                return ApiResponse::error('Validation failed', 422, ['from_path' => ['A redirect for this path already exists.']]);
            }
        }
        $redirect->update($data);
        Cache::forget('redirects.map');

        return ApiResponse::success(new SimpleResource($redirect), 'Redirect updated successfully');
    }

    public function destroy(Redirect $redirect): JsonResponse
    {
        $redirect->delete();
        Cache::forget('redirects.map');

        return ApiResponse::success(null, 'Redirect deleted successfully');
    }
}
