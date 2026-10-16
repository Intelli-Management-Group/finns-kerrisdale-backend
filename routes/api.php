<?php

use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Public\ContentController;
use App\Http\Controllers\Api\Public\FormController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public API  (/api/public/*)  — read-only, cached, used by the React websites
|--------------------------------------------------------------------------
*/
Route::prefix('public')->middleware('throttle:api')->group(function () {
    Route::get('site', [ContentController::class, 'site']);
    Route::get('resolve/{slug}', [ContentController::class, 'resolve'])->where('slug', '[A-Za-z0-9\-_]+');
    Route::get('pages', [ContentController::class, 'pages']);
    Route::get('pages/{slug}', [ContentController::class, 'page']);
    Route::get('posts', [ContentController::class, 'posts']);
    Route::get('posts/{slug}', [ContentController::class, 'post']);
    Route::get('categories', [ContentController::class, 'categories']);
    Route::get('brands', [ContentController::class, 'brands']);
    Route::get('galleries', [ContentController::class, 'galleries']);
    Route::get('galleries/{slug}', [ContentController::class, 'gallery']);

    Route::middleware('throttle:forms')->group(function () {
        Route::post('subscribers', [FormController::class, 'subscribe']);
        Route::post('contact', [FormController::class, 'contact']);
        Route::post('unsubscribe/{token}', [FormController::class, 'unsubscribe']);
    });
});

/*
|--------------------------------------------------------------------------
| Authentication (/api/auth/*)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

/*
|--------------------------------------------------------------------------
| Admin API (/api/admin/*) — Sanctum bearer token, admin or editor role
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware(['auth:sanctum', 'active', 'throttle:admin'])->group(function () {
    Route::get('dashboard', Admin\DashboardController::class);
    Route::put('profile', [Admin\ProfileController::class, 'update']);

    // Pages
    Route::patch('pages/{page}/status', [Admin\PageController::class, 'status']);
    Route::post('pages/{id}/restore', [Admin\PageController::class, 'restore']);
    Route::delete('pages/{id}/force', [Admin\PageController::class, 'forceDelete'])->middleware('can:admin');
    Route::apiResource('pages', Admin\PageController::class);

    // Posts
    Route::post('posts/bulk', [Admin\PostController::class, 'bulk']);
    Route::patch('posts/{post}/status', [Admin\PostController::class, 'status']);
    Route::post('posts/{id}/restore', [Admin\PostController::class, 'restore']);
    Route::delete('posts/{id}/force', [Admin\PostController::class, 'forceDelete'])->middleware('can:admin');
    Route::apiResource('posts', Admin\PostController::class);

    // Taxonomies, brands, galleries
    Route::apiResource('categories', Admin\CategoryController::class);
    Route::apiResource('tags', Admin\TagController::class)->except('show');
    Route::put('brands/order', [Admin\BrandController::class, 'reorder']);
    Route::apiResource('brands', Admin\BrandController::class);
    Route::apiResource('galleries', Admin\GalleryController::class);

    // Media library
    Route::get('media/folders', [Admin\MediaController::class, 'folders']);
    Route::post('media/{media}/replace', [Admin\MediaController::class, 'replace']);
    Route::apiResource('media', Admin\MediaController::class)->parameters(['media' => 'media']);

    // Inbox
    Route::get('subscribers/export', [Admin\SubscriberController::class, 'export']);
    Route::apiResource('subscribers', Admin\SubscriberController::class)->only(['index', 'update', 'destroy']);
    Route::apiResource('contact-submissions', Admin\ContactSubmissionController::class)->only(['index', 'show', 'update', 'destroy']);

    // Admin-only areas
    Route::middleware('can:admin')->group(function () {
        Route::apiResource('users', Admin\UserController::class);
        Route::apiResource('redirects', Admin\RedirectController::class)->except('show');
        Route::get('settings', [Admin\SettingController::class, 'index']);
        Route::put('settings', [Admin\SettingController::class, 'update']);
        Route::get('menu', [Admin\MenuController::class, 'index']);
        Route::put('menu', [Admin\MenuController::class, 'update']);
    });
});
