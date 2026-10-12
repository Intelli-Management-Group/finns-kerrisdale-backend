<?php

return [
    // Public site URL (used for canonical links, sitemap, feed)
    'url' => rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/'),

    // Built React website. Laravel serves its index.html with SEO meta tags filled in per URL,
    // so crawlers and link previews get the right title/description/image without running JS.
    'dist_path' => env('FRONTEND_DIST_PATH', base_path('../frontend/dist')),
];
