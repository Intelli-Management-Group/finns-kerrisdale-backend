<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Consistent JSON envelope: { success, message, data, meta? } / { success:false, message, errors? }
 */
class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'OK', int $status = 200, array $meta = []): JsonResponse
    {
        if ($data instanceof ResourceCollection && $data->resource instanceof LengthAwarePaginator) {
            $p = $data->resource;
            $meta = array_merge(self::paginationMeta($p), $meta);
            $data = $data->collection->map(fn ($r) => $r instanceof JsonResource ? $r->resolve(request()) : $r)->all();
        } elseif ($data instanceof JsonResource) {
            $data = $data->resolve(request());
        } elseif ($data instanceof LengthAwarePaginator) {
            $meta = array_merge(self::paginationMeta($data), $meta);
            $data = $data->items();
        }

        $body = ['success' => true, 'message' => $message, 'data' => $data];
        if ($meta) {
            $body['meta'] = $meta;
        }

        return response()->json($body, $status);
    }

    public static function created(mixed $data = null, string $message = 'Created successfully'): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function error(string $message, int $status = 400, array $errors = []): JsonResponse
    {
        $body = ['success' => false, 'message' => $message];
        if ($errors) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $status);
    }

    /**
     * Fully resolve a resource (including nested resources) to plain arrays, so it can be cached
     * safely — cache stores serialize values, and unresolved nested resources don't survive that.
     */
    public static function plain(JsonResource $resource): array
    {
        return json_decode(json_encode($resource->resolve(request())), true) ?? [];
    }

    public static function paginationMeta(LengthAwarePaginator $p): array
    {
        return [
            'current_page' => $p->currentPage(),
            'last_page' => $p->lastPage(),
            'per_page' => $p->perPage(),
            'total' => $p->total(),
            'from' => $p->firstItem(),
            'to' => $p->lastItem(),
        ];
    }
}
