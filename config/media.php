<?php

return [

    // Storage disk for uploads (public = storage/app/public, served at /storage via `php artisan storage:link`)
    'disk' => env('MEDIA_DISK', 'public'),

    // Base folder inside the disk: uploads/{folder}/{Y}/{m}/{file}
    'base_path' => 'uploads',

    'folders' => ['pages', 'posts', 'galleries', 'gallery-photos', 'users', 'general', 'brands'],

    // Image driver: gd (always available) or imagick
    'driver' => env('IMAGE_DRIVER', 'gd'),

    // Resize anything larger than this, keeping the aspect ratio. Smaller images are never upscaled.
    'max_width' => (int) env('IMAGE_MAX_WIDTH', 2000),
    'max_height' => (int) env('IMAGE_MAX_HEIGHT', 2000),

    // 1-100. 80-85 keeps product photos crisp; lower values start to show artefacts on fabric textures.
    'quality' => (int) env('IMAGE_QUALITY', 82),

    // webp | original. "original" keeps JPEG/PNG and only recompresses them.
    'format' => env('IMAGE_FORMAT', 'webp'),

    // Extra sizes generated for responsive images (longest side, px)
    'variants' => [
        'thumb' => 300,
        'medium' => 768,
        'large' => 1200,
    ],

    'max_upload_kb' => (int) env('IMAGE_MAX_UPLOAD_KB', 15360), // 15 MB

    'allowed_mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif'],

    /*
    | Origin images are served from. Leave MEDIA_BASE_URL empty to fall back to
    | ASSET_URL, then APP_URL. In local dev with React on another port this
    | must be the Laravel origin (e.g. http://localhost:8000), otherwise the
    | browser asks the Vite dev server for /uploads/... and gets a 404.
    */
    'base_url' => env('MEDIA_BASE_URL') ?: (env('ASSET_URL') ?: env('APP_URL', '')),

    /* Folder inside public/ that holds the copied WordPress uploads. */
    'uploads_dir' => env('MEDIA_UPLOADS_DIR', 'uploads'),

    /*
    | Hostnames of the old WordPress site. URLs on these hosts are treated as
    | legacy and mapped; any other host (CDNs, embeds) is left untouched.
    */
    'legacy_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'MEDIA_LEGACY_HOSTS',
        'finnskerrisdaleladies.com,www.finnskerrisdaleladies.com'
    ))))),

    /* Log every missing / unreadable / fallback reference (channel: default). */
    'log' => env('MEDIA_LOG', true),

    /*
    | Columns that hold image references, scanned by `php artisan media:audit`
    | and rewritten by `media:audit --write`. Adjust to your schema.
    | 'table' => ['primary_key' => 'id', 'columns' => ['col', ...]]
    */
    'audit' => [
        'columns' => [
            // 'posts'    => ['primary_key' => 'id', 'columns' => ['content', 'excerpt', 'featured_image']],
            // 'pages'    => ['primary_key' => 'id', 'columns' => ['content', 'hero_image']],
            // 'events'   => ['primary_key' => 'id', 'columns' => ['description', 'image']],
            // 'settings' => ['primary_key' => 'id', 'columns' => ['value']],
        ],

        /* Frontend source folders scanned by `media:audit --code`. Relative to base_path(). */
        'code_paths' => ['../frontend/src', 'resources/js', 'resources/css'],

        'code_extensions' => ['js', 'jsx', 'ts', 'tsx', 'css', 'scss', 'sass', 'less', 'html', 'php'],
    ],
];
