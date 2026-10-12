<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SimpleResource;
use App\Models\Subscriber;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Subscriber::query();
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return ApiResponse::success(SimpleResource::collection($this->paginateQuery($request, $query, ['email'], ['email', 'status', 'created_at', 'id'], '-id')), 'Subscribers retrieved successfully');
    }

    public function update(Request $request, Subscriber $subscriber): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:pending,confirmed,unsubscribed']]);
        $subscriber->update($data + ['unsubscribed_at' => $data['status'] === 'unsubscribed' ? now() : null]);

        return ApiResponse::success(new SimpleResource($subscriber), 'Subscriber updated');
    }

    public function destroy(Subscriber $subscriber): JsonResponse
    {
        $subscriber->delete();

        return ApiResponse::success(null, 'Subscriber deleted');
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'status', 'source', 'subscribed_at', 'confirmed_at', 'unsubscribed_at']);
            Subscriber::query()->orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $s) {
                    fputcsv($out, [$s->email, $s->status, $s->source, $s->created_at, $s->confirmed_at, $s->unsubscribed_at]);
                }
            });
            fclose($out);
        }, 'subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
