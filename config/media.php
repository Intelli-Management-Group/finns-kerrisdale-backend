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
];
