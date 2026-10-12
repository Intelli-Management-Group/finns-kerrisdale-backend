<?php

use App\Http\Controllers\Web\SeoFilesController;
use App\Http\Controllers\Web\SpaController;
use Illuminate\Support\Facades\Route;

Route::get('sitemap.xml', [SeoFilesController::class, 'sitemap']);
Route::get('robots.txt', [SeoFilesController::class, 'robots']);
Route::get('feed', [SeoFilesController::class, 'feed']);
Route::get('feed/', [SeoFilesController::class, 'feed']);

// Everything else is the React website (meta tags rendered server-side for SEO).
Route::get('/{any?}', SpaController::class)->where('any', '^(?!api/|storage/|up$).*$');
