<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SimpleResource;
use App\Models\ContactSubmission;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactSubmissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ContactSubmission::query();
        if ($request->query('read') === '0') {
            $query->whereNull('read_at');
        } elseif ($request->query('read') === '1') {
            $query->whereNotNull('read_at');
        }
        $page = $this->paginateQuery($request, $query, ['name', 'email', 'subject', 'message'], ['name', 'email', 'created_at', 'id'], '-id');

        return ApiResponse::success(SimpleResource::collection($page), 'Contact submissions retrieved successfully', 200, [
            'unread' => ContactSubmission::whereNull('read_at')->count(),
        ]);
    }

    public function show(ContactSubmission $contactSubmission): JsonResponse
    {
        if (! $contactSubmission->read_at) {
            $contactSubmission->update(['read_at' => now()]);
        }

        return ApiResponse::success(new SimpleResource($contactSubmission), 'Contact submission retrieved');
    }

    public function update(Request $request, ContactSubmission $contactSubmission): JsonResponse
    {
        $read = $request->validate(['read' => ['required', 'boolean']])['read'];
        $contactSubmission->update(['read_at' => $read ? now() : null]);

        return ApiResponse::success(new SimpleResource($contactSubmission), $read ? 'Marked as read' : 'Marked as unread');
    }

    public function destroy(ContactSubmission $contactSubmission): JsonResponse
    {
        $contactSubmission->delete();

        return ApiResponse::success(null, 'Contact submission deleted');
    }
}
