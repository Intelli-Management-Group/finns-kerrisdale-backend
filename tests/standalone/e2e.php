<?php
// End-to-end: scanner, folder check, SQLite rewrite + rollback, HTTP fetch of resolved URLs.
// php tests/standalone/e2e.php
foreach (['ImageUrlResolver', 'ImageContentRewriter', 'MediaScanner'] as $c) require __DIR__ . "/../../app/Support/Media/$c.php";
use App\Support\Media\{ImageUrlResolver as R, ImageContentRewriter as W, MediaScanner as S};

$root = sys_get_temp_dir() . '/media-e2e-' . getmypid();
$pub = "$root/public";
@mkdir("$pub/uploads/2024/05", 0777, true);
foreach (['2024/05/image.jpg', '2024/05/hero-scaled.jpg'] as $f) file_put_contents("$pub/uploads/$f", base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='));
file_put_contents("$pub/uploads/.htaccess", "Deny from all\n");
@mkdir("$root/src/components", 0777, true);
file_put_contents("$root/src/components/Hero.jsx", <<<'JSX'
export default function Hero() {
  return <section style={{ backgroundImage: "url('/wp-content/uploads/2024/05/hero.jpg')" }}>
    <img src="https://example.com/wp-content/uploads/2024/05/image.jpg" alt="" />
    <img src={`${API}/wp-content/uploads/${post.file}`} />
    <img src="/uploads/2024/05/image.jpg" />
    <img src="/images/logo.svg" />
    <img src="https://example.com/wp-content/uploads/2021/02/missing.png" />
  </section>;
}
JSX);
file_put_contents("$root/src/app.css", ".banner{background:url(../wp-content/uploads/2024/05/image-768x512.jpg) center/cover}\n");

$port = 8765;
$r = new R($pub, 'uploads', "http://127.0.0.1:$port", ['example.com']);
$w = new W($r); $s = new S($r);
$fail = 0; $n = 0;
function ok($label, $cond) { global $fail, $n; $n++; if (!$cond) { $fail++; echo "FAIL $label\n"; } }

// 1. Code scan
$f = $s->scanCode(["$root/src"], ['jsx', 'css']);
$by = []; foreach ($f as $x) $by[basename($x['file']) . ':' . $x['line']][] = $x;
echo "Code findings:\n"; foreach ($f as $x) printf("  %-14s %s:%d  %s%s\n", $x['status'], basename($x['file']), $x['line'], $x['ref'], $x['proposed'] ? "  -> {$x['proposed']}" : '');
$st = array_column($f, 'status');
ok('code: fallback for hero (scaled) inside JSX url()', (bool) array_filter($f, fn ($x) => $x['ref'] === '/wp-content/uploads/2024/05/hero.jpg' && $x['status'] === 'fallback' && $x['proposed'] === '/uploads/2024/05/hero-scaled.jpg'));
ok('code: mapped absolute', in_array('mapped', $st));
ok('code: dynamic template literal', in_array('dynamic', $st));
ok('code: missing reported', in_array('missing', $st));
ok('code: ok path recognised', in_array('ok', $st));
ok('code: app asset ignored', !array_filter($f, fn ($x) => str_contains($x['ref'], 'logo.svg')));
ok('code: css resized variant', (bool) array_filter($f, fn ($x) => str_ends_with($x['file'], '.css') && $x['proposed'] === '/uploads/2024/05/image.jpg'));

// 2. Folder check
$issues = $s->checkUploadsFolder("$pub/uploads");
ok('folder: htaccess flagged', (bool) array_filter($issues, fn ($i) => str_ends_with($i['path'], '.htaccess')));

// 3. DB rewrite + rollback (same loop shape as MediaAudit)
$db = new PDO('sqlite::memory:'); $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('create table posts (id integer primary key, content text, featured_image text, meta text)');
$ins = $db->prepare('insert into posts values (?,?,?,?)');
$ins->execute([1, '<p><img src="https://example.com/wp-content/uploads/2024/05/image-300x200.jpg" srcset="https://example.com/wp-content/uploads/2024/05/image-300x200.jpg 300w"></p>', 'https://example.com/wp-content/uploads/2024/05/hero.jpg', serialize(['thumb' => '2024/05/image.jpg'])]);
$ins->execute([2, '<img src="https://example.com/wp-content/uploads/2021/02/missing.png">', '/uploads/2024/05/image.jpg', null]);
$changes = [];
foreach ($db->query('select * from posts')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    foreach (['content', 'featured_image', 'meta'] as $col) {
        if ($row[$col] === null) continue;
        $new = $w->rewrite($row[$col], W::MODE_PATH);
        if ($new !== $row[$col]) {
            $changes[] = [$row['id'], $col, $row[$col]];
            $db->prepare("update posts set $col=? where id=?")->execute([$new, $row['id']]);
        }
    }
}
$p1 = $db->query('select * from posts where id=1')->fetch(PDO::FETCH_ASSOC);
$p2 = $db->query('select * from posts where id=2')->fetch(PDO::FETCH_ASSOC);
ok('db: content src', str_contains($p1['content'], 'src="/uploads/2024/05/image.jpg"'));
ok('db: featured scaled', $p1['featured_image'] === '/uploads/2024/05/hero-scaled.jpg');
ok('db: serialized intact', unserialize($p1['meta'])['thumb'] === '/uploads/2024/05/image.jpg');
ok('db: missing untouched', str_contains($p2['content'], 'https://example.com/wp-content/uploads/2021/02/missing.png'));
ok('db: correct value untouched', count(array_filter($changes, fn ($c) => $c[0] == 2)) === 0);
foreach ($changes as [$id, $col, $old]) $db->prepare("update posts set $col=? where id=?")->execute([$old, $id]);
ok('db: rollback', $db->query('select featured_image from posts where id=1')->fetchColumn() === 'https://example.com/wp-content/uploads/2024/05/hero.jpg');

// 4. Serve public/ and fetch every resolved URL
unlink("$pub/uploads/.htaccess"); // built-in server ignores it anyway
$proc = proc_open(['php', '-S', "127.0.0.1:$port", '-t', $pub], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
usleep(400000);
$refs = ['https://example.com/wp-content/uploads/2024/05/image.jpg', '/wp-content/uploads/2024/05/hero.jpg',
         '2024/05/image-300x200.jpg', '/uploads/2024/05/image.jpg'];
echo "HTTP:\n";
foreach ($refs as $ref) {
    $url = $r->toUrl($ref);
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'proxy' => '']]);
    $body = @file_get_contents($url, false, $ctx);
    $code = isset($http_response_header[0]) ? (int) explode(' ', $http_response_header[0])[1] : 0;
    $type = implode('', preg_grep('/^Content-Type/i', $http_response_header ?? []));
    printf("  %d %-45s <- %s\n", $code, $url, $ref);
    ok("http 200 $ref", $code === 200 && stripos($type, 'image/jpeg') !== false);
}
$missingUrl = "http://127.0.0.1:$port" . $r->inspect('https://example.com/wp-content/uploads/2021/02/missing.png')['proposed'];
@file_get_contents($missingUrl, false, stream_context_create(['http' => ['ignore_errors' => true]]));
printf("  %s %s (missing file stays a visible 404)\n", explode(' ', $http_response_header[0])[1], $missingUrl);
proc_terminate($proc);

exec('rm -rf ' . escapeshellarg($root));
echo ($n - $fail) . "/$n passed\n";
exit($fail ? 1 : 0);
