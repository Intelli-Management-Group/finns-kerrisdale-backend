<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Serves the built React website's index.html with per-URL SEO tags injected.
 * Search engines and social previews see real titles/descriptions/images; React then takes over.
 */
class SpaController extends Controller
{
    public function __invoke(Request $request, SeoService $seo): Response
    {
        $meta = $seo->forPath('/'.$request->path());
        $index = rtrim(config('frontend.dist_path'), '/').'/index.html';

        if (! is_file($index)) {
            $html = '<!doctype html><html><head><meta charset="utf-8"><title>'.e($meta['title']).'</title></head><body style="font-family:sans-serif;padding:40px">'
                .'<h1>Finns Kerrisdale Ladies API</h1><p>The React website has not been built yet. Run <code>npm run build</code> in <code>frontend/</code>, '
                .'or use the Vite dev server on <a href="'.e(config('frontend.url')).'">'.e(config('frontend.url')).'</a>.</p></body></html>';

            return response($html, $meta['status']);
        }

        $html = file_get_contents($index);
        $tags = $this->tags($meta);
        $html = preg_replace('#<title>.*?</title>#s', '<title>'.e($meta['title']).'</title>', $html, 1);
        $html = str_replace('<!--seo-->', $tags, $html, $count);
        if (! $count) {
            $html = str_replace('</head>', $tags."\n</head>", $html);
        }

        return response($html, $meta['status'])->header('Content-Type', 'text/html; charset=UTF-8');
    }

    private function tags(array $m): string
    {
        $settings = app(\App\Services\SettingsService::class);
        $site = e($settings->get('site_name', 'Finns Kerrisdale Ladies'));
        $t = [
            '<meta name="description" content="'.e($m['description']).'">',
            '<link rel="canonical" href="'.e($m['canonical']).'">',
            '<meta name="robots" content="'.($m['noindex'] ? 'noindex, nofollow' : 'index, follow, max-image-preview:large').'">',
            '<meta property="og:locale" content="en_US">',
            '<meta property="og:site_name" content="'.$site.'">',
            '<meta property="og:type" content="'.e($m['type']).'">',
            '<meta property="og:title" content="'.e($m['title']).'">',
            '<meta property="og:description" content="'.e($m['description']).'">',
            '<meta property="og:url" content="'.e($m['canonical']).'">',
            '<meta name="twitter:card" content="'.($m['image'] ? 'summary_large_image' : 'summary').'">',
            '<meta name="twitter:title" content="'.e($m['title']).'">',
            '<meta name="twitter:description" content="'.e($m['description']).'">',
        ];
        if ($m['image']) {
            $t[] = '<meta property="og:image" content="'.e($m['image']).'">';
            $t[] = '<meta name="twitter:image" content="'.e($m['image']).'">';
        }
        // Settings → "Google Search Console verification code" (accepts the code or the whole meta tag)
        $gsv = trim((string) $settings->get('google_site_verification', ''));
        if (preg_match('/content="([^"]+)"/', $gsv, $g)) {
            $gsv = $g[1];
        }
        if ($gsv !== '' && ($m['canonical'] ?? '') !== '' && parse_url($m['canonical'], PHP_URL_PATH) === '/') {
            $t[] = '<meta name="google-site-verification" content="'.e($gsv).'">';
        }
        foreach ($m['jsonld'] as $ld) {
            $t[] = '<script type="application/ld+json">'.json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP).'</script>';
        }

        return implode("\n    ", $t);
    }
}
