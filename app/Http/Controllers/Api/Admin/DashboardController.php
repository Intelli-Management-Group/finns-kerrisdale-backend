<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostListResource;
use App\Http\Resources\SimpleResource;
use App\Models\ContactSubmission;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\Subscriber;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return ApiResponse::success([
            'stats' => [
                'posts_published' => Post::where('status', 'published')->count(),
                'posts_draft' => Post::where('status', 'draft')->count(),
                'pages' => Page::count(),
                'media' => Media::count(),
                'media_bytes' => (int) Media::sum('size_bytes'),
                'media_saved_bytes' => (int) Media::whereNotNull('original_size_bytes')->sum('original_size_bytes') - (int) Media::whereNotNull('original_size_bytes')->sum('size_bytes'),
                'subscribers' => Subscriber::where('status', 'confirmed')->count(),
                'contact_unread' => ContactSubmission::whereNull('read_at')->count(),
                'users' => User::count(),
            ],
            'recent_posts' => PostListResource::collection(Post::with('author:id,name')->latest('updated_at')->limit(6)->get())->resolve($request),
            'recent_submissions' => SimpleResource::collection(ContactSubmission::latest()->limit(5)->get())->resolve($request),
            'recent_subscribers' => SimpleResource::collection(Subscriber::latest()->limit(5)->get())->resolve($request),
            'posts_per_year' => Post::where('status', 'published')->get(['published_at'])
                ->groupBy(fn ($p) => $p->published_at?->format('Y'))->map->count()->sortKeys()->all(),
        ], 'Dashboard data');
    }
}
