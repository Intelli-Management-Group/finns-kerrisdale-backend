<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Admin-only areas (users, settings, redirects, menu, permanent deletes). Editors manage content.
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(240)->by($r->ip()));
        RateLimiter::for('admin', fn (Request $r) => Limit::perMinute(600)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()),
            Limit::perMinute(20)->by($r->ip()),
        ]);
        RateLimiter::for('forms', fn (Request $r) => Limit::perMinute(6)->by($r->ip()));
    }
}
