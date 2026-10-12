<?php

namespace App\Providers;

use App\Console\Commands\MediaAudit;
use App\Support\Media\ImageContentRewriter;
use App\Support\Media\ImageUrlResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ImageUrlResolver::class, function () {
            $logger = config('media.log')
                ? fn (string $level, string $message, array $context) => Log::log($level, $message, $context)
                : null;

            return new ImageUrlResolver(
                publicRoot: public_path(),
                uploadsDir: config('media.uploads_dir', 'uploads'),
                baseUrl: (string) config('media.base_url', ''),
                legacyHosts: config('media.legacy_hosts', []),
                logger: $logger,
            );
        });

        $this->app->singleton(ImageContentRewriter::class, fn ($app) => new ImageContentRewriter(
            $app->make(ImageUrlResolver::class)
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([MediaAudit::class]);
        }
    }
}
