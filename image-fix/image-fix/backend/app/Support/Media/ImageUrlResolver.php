<?php

namespace App\Support\Media;

/**
 * Single source of truth for turning any image reference found in the
 * migrated WordPress data (or in code) into a working URL.
 *
 * Handles:
 *   https://old-domain.com/wp-content/uploads/2024/05/a.jpg   (legacy absolute)
 *   //old-domain.com/wp-content/uploads/2024/05/a.jpg         (protocol-relative)
 *   /wp-content/uploads/2024/05/a.jpg  wp-content/uploads/... (legacy relative)
 *   ../wp-content/uploads/2024/05/a.jpg                        (dot-relative)
 *   /uploads/2024/05/a.jpg  uploads/2024/05/a.jpg              (new layout)
 *   2024/05/a.jpg                                              (bare WP "file" value)
 *   a-300x200.jpg variants whose resized copy was never migrated
 *   a.jpg where only a-scaled.jpg exists (WP 5.3+ big-image handling)
 *   filename case mismatches (Windows/macOS hosting -> Linux)
 *
 * Leaves untouched: external hosts (CDNs, YouTube thumbs...), data:/blob: URIs,
 * and paths that are not upload references (e.g. /images/logo.svg bundled
 * with the React app).
 *
 * Never renames, moves or deletes files. It only reads the filesystem.
 * It has no Laravel dependency so it can be unit tested in isolation.
 */
class ImageUrlResolver
{
    public const OK = 'ok';                 // already correct, file exists
    public const MAPPED = 'mapped';         // rewritten, file exists
    public const FALLBACK = 'fallback';     // exact file missing, nearest real file used
    public const MISSING = 'missing';       // looks like an upload, no file on disk
    public const UNREADABLE = 'unreadable'; // file exists but PHP can't read it
    public const UNMAPPABLE = 'unmappable'; // old-domain URL outside /wp-content/uploads
    public const EXTERNAL = 'external';     // other host, left alone
    public const UNCHANGED = 'unchanged';   // not an upload reference, left alone

    private const IMAGE_EXT = 'jpe?g|png|gif|webp|avif|svg|bmp|ico|tiff?|heic';

    /** @var array<string, array> */
    private array $cache = [];

    /** @var array<string, array<string,string>|null> lowercase filename map per directory */
    private array $dirIndex = [];

    /** @var \Closure|null fn(string $level, string $message, array $context) */
    private ?\Closure $logger;

    /**
     * @param string   $publicRoot   absolute path of Laravel's public/ directory
     * @param string   $uploadsDir   folder inside public/ that holds the WP uploads ("uploads")
     * @param string   $baseUrl      origin images are served from, e.g. https://example.com ("" = root-relative)
     * @param string[] $legacyHosts  hostnames of the old WordPress site (with and without www)
     */
    public function __construct(
        private string $publicRoot,
        private string $uploadsDir = 'uploads',
        private string $baseUrl = '',
        private array $legacyHosts = [],
        ?\Closure $logger = null,
    ) {
        $this->publicRoot = rtrim($publicRoot, '/\\');
        $this->uploadsDir = trim($uploadsDir, '/');
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->legacyHosts = array_values(array_filter(array_map(
            fn ($h) => strtolower(trim((string) $h)),
            $legacyHosts
        )));
        $this->logger = $logger;
    }

    /** Absolute URL for API responses / rendering. */
    public function toUrl(?string $ref): ?string
    {
        return $this->inspect($ref)['url'];
    }

    /** Root-relative path ("/uploads/2024/05/a.jpg") for storing in the database. */
    public function toPath(?string $ref): ?string
    {
        return $this->inspect($ref)['path'];
    }

    /**
     * Full diagnosis of one reference.
     *
     * @return array{input:?string,status:string,url:?string,path:?string,file:?string,note:?string}
     */
    public function inspect(?string $ref): array
    {
        $key = (string) $ref;
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        return $this->cache[$key] = $this->doInspect($ref);
    }

    /** True when the string is (or contains, as a URL/path) a reference this class can map. */
    public function isUploadReference(string $ref): bool
    {
        return $this->extractUploadRelative($ref) !== null;
    }

    public function legacyHosts(): array
    {
        return $this->legacyHosts;
    }

    public function uploadsDir(): string
    {
        return $this->uploadsDir;
    }

    // ---------------------------------------------------------------------

    private function doInspect(?string $ref): array
    {
        $result = [
            'input' => $ref, 'status' => self::UNCHANGED,
            'url' => $ref, 'path' => $ref, 'file' => null, 'note' => null,
        ];

        $raw = trim((string) $ref);
        if ($raw === '') {
            $result['url'] = $result['path'] = ($ref === null ? null : $ref);
            return $result;
        }

        if (preg_match('#^(data|blob|mailto|tel|javascript):#i', $raw)) {
            return $result;
        }

        [$location, $suffix] = $this->splitSuffix($raw);
        $host = $this->hostOf($location);

        if ($host !== null && !$this->isLegacyHost($host) && !$this->isOwnHost($host)) {
            $result['status'] = self::EXTERNAL;
            return $result;
        }

        $relative = $this->extractUploadRelative($location);

        if ($relative === null) {
            if ($host !== null && $this->isLegacyHost($host)
                && preg_match('#\.(?:' . self::IMAGE_EXT . ')$#i', $location)) {
                // e.g. https://old.com/wp-content/themes/x/img/hero.jpg — the theme
                // folder was not migrated, so there is nothing to map it to.
                $result['status'] = self::UNMAPPABLE;
                $result['note'] = 'Old-domain URL outside wp-content/uploads; copy the file into public/ and update the reference by hand.';
                $this->log('warning', 'Unmappable legacy image URL', ['ref' => $ref]);
            }
            return $result;
        }

        $alreadyCorrect = $this->isAlreadyCanonical($location, $host);
        [$found, $how] = $this->locate($relative);

        if ($found === null) {
            // Do not rewrite a reference to a file we cannot see: leave the
            // original untouched, report it, and let the frontend placeholder +
            // console warning surface it. 'proposed' shows where it would go.
            $result['status'] = self::MISSING;
            $result['proposed'] = $this->buildPath($relative) . $suffix;
            $result['note'] = 'No file at public/' . $this->uploadsDir . '/' . $relative;
            $this->log('warning', 'Image file not found', ['ref' => $ref, 'expected' => $this->absolute($relative)]);
            return $result;
        }

        $result['file'] = $this->absolute($found);
        $result['path'] = $this->buildPath($found) . $suffix;
        $result['url'] = $this->buildUrl($found) . $suffix;

        if (!is_readable($result['file'])) {
            $result['status'] = self::UNREADABLE;
            $result['note'] = 'File exists but is not readable; check ownership/permissions.';
            $this->log('error', 'Image file not readable', ['file' => $result['file']]);
            return $result;
        }

        if ($how !== 'exact') {
            $result['status'] = self::FALLBACK;
            $result['note'] = "Used {$found} ({$how}) because {$relative} does not exist.";
            $this->log('info', 'Image resolved via fallback', ['ref' => $ref, 'used' => $found, 'reason' => $how]);
        } else {
            $result['status'] = $alreadyCorrect ? self::OK : self::MAPPED;
        }

        return $result;
    }

    /**
     * Returns the path relative to public/uploads (decoded, no leading slash),
     * or null when the reference is not an upload reference.
     */
    private function extractUploadRelative(string $location): ?string
    {
        $path = $location;
        if (str_starts_with($path, '//') || preg_match('#^[a-z][a-z0-9+.-]*://#i', $path)) {
            $path = (string) parse_url(str_starts_with($path, '//') ? 'https:' . $path : $path, PHP_URL_PATH);
        }
        $path = str_replace('\\', '/', $path);

        $uploads = preg_quote($this->uploadsDir, '#');

        // Any depth: /wp-content/uploads/..., ../../wp-content/uploads/..., /blog/wp-content/uploads/...
        if (preg_match('#(?:^|/)wp-content/uploads/(.+)$#i', $path, $m)) {
            return $this->cleanRelative($m[1]);
        }

        // New layout: /uploads/..., uploads/..., ./uploads/...
        if (preg_match('#^(?:\./|/)?' . $uploads . '/(.+)$#', $path, $m)) {
            return $this->cleanRelative($m[1]);
        }

        // Bare WordPress "_wp_attached_file" style value: 2024/05/a.jpg
        if (preg_match('#^/?((?:19|20)\d{2}/(?:0[1-9]|1[0-2])/[^/]+\.(?:' . self::IMAGE_EXT . '))$#i', $path, $m)) {
            return $this->cleanRelative($m[1]);
        }

        return null;
    }

    private function cleanRelative(string $rel): ?string
    {
        $rel = rawurldecode($rel);
        $parts = [];
        foreach (explode('/', $rel) as $seg) {
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                return null; // never resolve outside the uploads folder
            }
            $parts[] = $seg;
        }
        return $parts ? implode('/', $parts) : null;
    }

    /**
     * Find a real file for the relative path. Returns [relativeFound|null, how].
     */
    private function locate(string $rel): array
    {
        if (is_file($this->absolute($rel))) {
            return [$rel, 'exact'];
        }

        $dir = dirname($rel) === '.' ? '' : dirname($rel) . '/';
        $file = basename($rel);
        $candidates = [];

        if (preg_match('/^(.+?)(-\d+x\d+)(\.[a-z0-9]+)$/i', $file, $m)) {
            // a-300x200.jpg -> a.jpg / a-scaled.jpg (resized copy never migrated)
            $candidates[] = [$dir . $m[1] . $m[3], 'original of resized variant'];
            $candidates[] = [$dir . $m[1] . '-scaled' . $m[3], 'scaled original of resized variant'];
        } elseif (preg_match('/^(.+?)(\.[a-z0-9]+)$/i', $file, $m)) {
            if (str_ends_with($m[1], '-scaled')) {
                $candidates[] = [$dir . substr($m[1], 0, -7) . $m[2], 'unscaled original'];
            } else {
                $candidates[] = [$dir . $m[1] . '-scaled' . $m[2], 'WordPress -scaled copy'];
            }
        }

        foreach ($candidates as [$candidate, $how]) {
            if (is_file($this->absolute($candidate))) {
                return [$candidate, $how];
            }
        }

        // Case-insensitive match inside the same directory.
        foreach (array_merge([[$rel, 'exact']], $candidates) as [$candidate, $how]) {
            $hit = $this->caseInsensitive($candidate);
            if ($hit !== null) {
                return [$hit, 'case-insensitive match'];
            }
        }

        return [null, 'none'];
    }

    private function caseInsensitive(string $rel): ?string
    {
        $dir = dirname($rel) === '.' ? '' : dirname($rel);
        if (!array_key_exists($dir, $this->dirIndex)) {
            $abs = $this->absolute($dir);
            $this->dirIndex[$dir] = null;
            if (is_dir($abs) && ($entries = @scandir($abs)) !== false) {
                $map = [];
                foreach ($entries as $e) {
                    $map[strtolower($e)] ??= $e;
                }
                $this->dirIndex[$dir] = $map;
            }
        }
        $real = $this->dirIndex[$dir][strtolower(basename($rel))] ?? null;
        return $real === null ? null : ($dir === '' ? $real : $dir . '/' . $real);
    }

    private function isAlreadyCanonical(string $location, ?string $host): bool
    {
        if ($host !== null && !$this->isOwnHost($host)) {
            return false;
        }
        $path = $host !== null
            ? (string) parse_url(str_starts_with($location, '//') ? 'https:' . $location : $location, PHP_URL_PATH)
            : $location;
        return str_starts_with($path, '/' . $this->uploadsDir . '/');
    }

    private function buildPath(string $rel): string
    {
        $encoded = implode('/', array_map('rawurlencode', explode('/', $rel)));
        return '/' . $this->uploadsDir . '/' . $encoded;
    }

    private function buildUrl(string $rel): string
    {
        return $this->baseUrl . $this->buildPath($rel);
    }

    private function absolute(string $rel): string
    {
        return $this->publicRoot . '/' . $this->uploadsDir . ($rel === '' ? '' : '/' . $rel);
    }

    /** Split off ?query and #fragment so they survive the rewrite. */
    private function splitSuffix(string $ref): array
    {
        $pos = strcspn($ref, '?#');
        return [substr($ref, 0, $pos), substr($ref, $pos)];
    }

    private function hostOf(string $location): ?string
    {
        if (str_starts_with($location, '//')) {
            $location = 'https:' . $location;
        } elseif (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $location)) {
            return null;
        }
        $host = parse_url($location, PHP_URL_HOST);
        return $host ? strtolower($host) : null;
    }

    private function isLegacyHost(string $host): bool
    {
        return in_array($host, $this->legacyHosts, true);
    }

    private function isOwnHost(string $host): bool
    {
        if ($this->baseUrl === '') {
            return false;
        }
        $own = parse_url($this->baseUrl, PHP_URL_HOST);
        return $own !== null && strtolower($own) === $host;
    }

    private function log(string $level, string $message, array $context): void
    {
        if ($this->logger) {
            ($this->logger)($level, '[media] ' . $message, $context);
        }
    }
}
