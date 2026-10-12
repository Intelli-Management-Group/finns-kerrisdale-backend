<?php
// Framework-free tests: php tests/standalone/run.php
require __DIR__ . '/../../app/Support/Media/ImageUrlResolver.php';
require __DIR__ . '/../../app/Support/Media/ImageContentRewriter.php';

use App\Support\Media\ImageUrlResolver as R;
use App\Support\Media\ImageContentRewriter as W;

$pub = sys_get_temp_dir() . '/media-test-' . getmypid();
@mkdir("$pub/uploads/2024/05", 0777, true);
@mkdir("$pub/uploads/2023/11", 0777, true);
foreach (['2024/05/image.jpg', '2024/05/hero-scaled.jpg', '2024/05/Team Photo.JPG', '2023/11/logo.png', '2024/05/locked.jpg'] as $f) {
    file_put_contents("$pub/uploads/$f", 'x');
}
chmod("$pub/uploads/2024/05/locked.jpg", 0000);

$log = [];
$r = new R($pub, 'uploads', 'https://new.test', ['example.com', 'www.example.com'], function ($l, $m, $c) use (&$log) { $log[] = "$l $m"; });
$w = new W($r);

$fail = 0; $n = 0;
function check($label, $got, $want) { global $fail, $n; $n++; if ($got !== $want) { $fail++; echo "FAIL $label\n  got:  " . var_export($got, true) . "\n  want: " . var_export($want, true) . "\n"; } }

// --- resolver: path formats
check('legacy absolute', $r->toPath('https://example.com/wp-content/uploads/2024/05/image.jpg'), '/uploads/2024/05/image.jpg');
check('legacy www + http', $r->toPath('http://www.example.com/wp-content/uploads/2024/05/image.jpg'), '/uploads/2024/05/image.jpg');
check('protocol relative', $r->toPath('//example.com/wp-content/uploads/2024/05/image.jpg'), '/uploads/2024/05/image.jpg');
check('legacy root-relative', $r->toPath('/wp-content/uploads/2024/05/image.jpg'), '/uploads/2024/05/image.jpg');
check('dot relative', $r->toPath('../../wp-content/uploads/2024/05/image.jpg'), '/uploads/2024/05/image.jpg');
check('new layout ok', $r->inspect('/uploads/2024/05/image.jpg')['status'], R::OK);
check('uploads no slash', $r->toPath('uploads/2024/05/image.jpg'), '/uploads/2024/05/image.jpg');
check('bare WP file', $r->toPath('2024/05/image.jpg'), '/uploads/2024/05/image.jpg');
check('to url', $r->toUrl('/wp-content/uploads/2024/05/image.jpg'), 'https://new.test/uploads/2024/05/image.jpg');
check('query kept', $r->toPath('https://example.com/wp-content/uploads/2024/05/image.jpg?ver=2'), '/uploads/2024/05/image.jpg?ver=2');
// --- fallbacks
check('resized variant', $r->toPath('/wp-content/uploads/2024/05/image-300x200.jpg'), '/uploads/2024/05/image.jpg');
check('scaled', $r->toPath('/wp-content/uploads/2024/05/hero.jpg'), '/uploads/2024/05/hero-scaled.jpg');
check('resized of scaled', $r->toPath('/wp-content/uploads/2024/05/hero-1024x683.jpg'), '/uploads/2024/05/hero-scaled.jpg');
$caseMatch = PHP_OS_FAMILY === 'Windows' ? '/uploads/2024/05/team%20photo.jpg' : '/uploads/2024/05/Team%20Photo.JPG';
check('case + space', $r->toPath('/wp-content/uploads/2024/05/team%20photo.jpg'), $caseMatch);
check('fallback status', $r->inspect('/wp-content/uploads/2024/05/image-300x200.jpg')['status'], R::FALLBACK);
// --- left alone / reported
$miss = $r->inspect('https://example.com/wp-content/uploads/2022/01/gone.jpg');
check('missing status', $miss['status'], R::MISSING);
check('missing not rewritten', $miss['url'], 'https://example.com/wp-content/uploads/2022/01/gone.jpg');
check('missing proposed', $miss['proposed'], '/uploads/2022/01/gone.jpg');
check('external untouched', $r->inspect('https://cdn.other.com/wp-content/uploads/2024/05/image.jpg')['status'], R::EXTERNAL);
check('app asset untouched', $r->toPath('/images/logo.svg'), '/images/logo.svg');
check('data uri', $r->toPath('data:image/png;base64,AAAA'), 'data:image/png;base64,AAAA');
check('theme image unmappable', $r->inspect('https://example.com/wp-content/themes/x/hero.jpg')['status'], R::UNMAPPABLE);
check('traversal blocked', $r->inspect('/uploads/../../.env')['status'], R::UNCHANGED);
check('own host absolute ok', $r->inspect('https://new.test/uploads/2024/05/image.jpg')['status'], R::OK);
check('storage media path', $r->toPath('/storage/uploads/posts/2024/03/image.webp'), '/storage/uploads/posts/2024/03/image.webp');
check('localhost storage media url', $r->toPath('http://localhost:8000/storage/uploads/posts/2024/03/image.webp'), '/storage/uploads/posts/2024/03/image.webp');
check('dynamic storage media url', $r->toUrl('http://localhost:8000/storage/uploads/posts/2024/03/image.webp'), 'https://new.test/storage/uploads/posts/2024/03/image.webp');
check('external storage url untouched', $r->toUrl('https://cdn.example.test/storage/uploads/posts/2024/03/image.webp'), 'https://cdn.example.test/storage/uploads/posts/2024/03/image.webp');
if (function_exists('posix_getuid') && posix_getuid() !== 0) check('unreadable', $r->inspect('/uploads/2024/05/locked.jpg')['status'], R::UNREADABLE);
check('null', $r->toPath(null), null);

// --- rewriter
$html = '<p><img class="wp-image-5" src="https://example.com/wp-content/uploads/2024/05/image-300x200.jpg" '
      . 'srcset="https://example.com/wp-content/uploads/2024/05/image-300x200.jpg 300w, https://example.com/wp-content/uploads/2024/05/image-1024x683.jpg 1024w" alt="a">'
      . '<a href="https://example.com/wp-content/uploads/2024/05/image.jpg">full</a> '
      . '<a href="https://example.com/about-us/">about</a>'
      . '<div style="background-image:url(\'/wp-content/uploads/2023/11/logo.png\')"></div>'
      . '<img src="https://example.com/wp-content/uploads/2022/01/gone.jpg"></p>';
$out = $w->rewrite($html, W::MODE_PATH);
check('html src', str_contains($out, 'src="/uploads/2024/05/image.jpg"'), true);
check('html srcset', str_contains($out, 'srcset="/uploads/2024/05/image.jpg 300w, /uploads/2024/05/image.jpg 1024w"'), true);
check('html href file', str_contains($out, 'href="/uploads/2024/05/image.jpg"'), true);
check('html page link kept', str_contains($out, 'href="https://example.com/about-us/"'), true);
check('html bg', str_contains($out, "url('/uploads/2023/11/logo.png')"), true);
check('html missing kept', str_contains($out, 'src="https://example.com/wp-content/uploads/2022/01/gone.jpg"'), true);
check('html other markup kept', str_contains($out, 'class="wp-image-5"') && str_contains($out, 'alt="a"'), true);

$block = '<!-- wp:image {"id":5,"url":"https:\/\/example.com\/wp-content\/uploads\/2024\/05\/image.jpg"} -->';
check('gutenberg escaped', $w->rewrite($block, W::MODE_PATH), '<!-- wp:image {"id":5,"url":"\/uploads\/2024\/05\/image.jpg"} -->');

$ser = serialize(['logo' => 'https://example.com/wp-content/uploads/2023/11/logo.png', 'n' => 3]);
$sout = $w->rewrite($ser, W::MODE_PATH);
check('serialized valid', unserialize($sout)['logo'] ?? null, '/uploads/2023/11/logo.png');

$json = '{"gallery":["https://example.com/wp-content/uploads/2024/05/image.jpg","2023/11/logo.png"]}';
check('json', $w->rewrite($json, W::MODE_PATH), '{"gallery":["/uploads/2024/05/image.jpg","/uploads/2023/11/logo.png"]}');
check('json untouched when no change', $w->rewrite('{"a": 1}', W::MODE_PATH), '{"a": 1}');

check('single url mode', $w->rewrite('https://example.com/wp-content/uploads/2024/05/image.jpg', W::MODE_URL), 'https://new.test/uploads/2024/05/image.jpg');
check('plain text with url', $w->rewrite('See https://example.com/wp-content/uploads/2024/05/image.jpg now', W::MODE_PATH), 'See /uploads/2024/05/image.jpg now');
check('idempotent', $w->rewrite($out, W::MODE_PATH), $out);

$w->scan($html);
$st = array_count_values(array_column($w->lastReferences(), 'status'));
check('scan sees missing', ($st[R::MISSING] ?? 0) >= 1, true);

chmod("$pub/uploads/2024/05/locked.jpg", 0644);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pub, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($iterator as $item) {
    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
}
rmdir($pub);
echo ($n - $fail) . "/$n passed\n";
exit($fail ? 1 : 0);
