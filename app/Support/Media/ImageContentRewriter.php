<?php

namespace App\Support\Media;

/**
 * Rewrites image references inside stored values using ImageUrlResolver.
 *
 * Understands:
 *   - a single URL/path value (featured_image, thumbnail, logo columns)
 *   - HTML (post content): src, data-src, data-lazy-src, poster, srcset,
 *     data-srcset, href to upload files, inline style url(...)
 *   - plain text / Gutenberg block comments containing old upload URLs,
 *     including JSON-escaped ones (https:\/\/old.com\/wp-content\/uploads\/...)
 *   - PHP-serialized values (WordPress options/meta) — unserialized, walked
 *     and re-serialized so string lengths stay valid
 *   - JSON values — decoded, walked, re-encoded (only when something changed)
 *
 * Only references whose target file exists are rewritten; everything else is
 * left byte-for-byte as it was.
 *
 * Mode MODE_PATH writes root-relative "/uploads/..." (use this for the DB so
 * no domain is hardcoded). MODE_URL writes absolute URLs (use for API output).
 */
class ImageContentRewriter
{
    public const MODE_PATH = 'path';
    public const MODE_URL = 'url';

    private const REWRITABLE = [
        ImageUrlResolver::OK,
        ImageUrlResolver::MAPPED,
        ImageUrlResolver::FALLBACK,
        ImageUrlResolver::UNREADABLE, // file exists; serving it will expose the permission problem
    ];

    /** @var array<int, array> references seen during the last rewrite() call */
    private array $seen = [];

    public function __construct(private ImageUrlResolver $resolver)
    {
    }

    public function rewrite(?string $value, string $mode = self::MODE_URL): ?string
    {
        $this->seen = [];
        if ($value === null || $value === '') {
            return $value;
        }
        return $this->rewriteAny($value, $mode);
    }

    /** Diagnoses (from ImageUrlResolver::inspect) for every reference found by the last rewrite(). */
    public function lastReferences(): array
    {
        return $this->seen;
    }

    /** Collect references without changing anything. */
    public function scan(?string $value): array
    {
        $this->rewrite($value, self::MODE_PATH);
        return $this->seen;
    }

    // ---------------------------------------------------------------------

    private function rewriteAny(string $value, string $mode): string
    {
        $trimmed = trim($value);

        if ($this->looksSerialized($trimmed)) {
            $data = @unserialize($trimmed, ['allowed_classes' => false]);
            if ($data !== false || $trimmed === 'b:0;') {
                $changed = false;
                $new = $this->walk($data, $mode, $changed);
                return $changed ? serialize($new) : $value;
            }
        }

        if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
            $data = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                $changed = false;
                $new = $this->walk($data, $mode, $changed);
                return $changed
                    ? json_encode($new, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                    : $value;
            }
        }

        return $this->rewriteString($value, $mode);
    }

    private function walk(mixed $data, string $mode, bool &$changed): mixed
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $data[$k] = $this->walk($v, $mode, $changed);
            }
            return $data;
        }
        if (is_string($data) && $data !== '') {
            $new = $this->rewriteAny($data, $mode);
            if ($new !== $data) {
                $changed = true;
            }
            return $new;
        }
        return $data;
    }

    private function rewriteString(string $value, string $mode): string
    {
        // A single reference: no whitespace and no markup.
        if (!preg_match('/[\s<>"\']/', trim($value))) {
            $trim = trim($value);
            $mapped = $this->map($trim, $mode, null, false);
            return $mapped === $trim ? $value : str_replace($trim, $mapped, $value);
        }

        $html = $value;

        // src / data-src / poster / href ... = "..."  or '...'
        $html = preg_replace_callback(
            '/(\s(?:src|data-src|data-lazy-src|data-orig-file|data-large-file|data-medium-file|data-full-url|poster|href)\s*=\s*)(["\'])(.*?)\2/i',
            fn ($m) => $m[1] . $m[2] . $this->map(html_entity_decode($m[3], ENT_QUOTES), $mode, $m[3]) . $m[2],
            $html
        );

        // srcset / data-srcset = "url 300w, url 768w"
        $html = preg_replace_callback(
            '/(\s(?:srcset|data-srcset|data-lazy-srcset)\s*=\s*)(["\'])(.*?)\2/i',
            fn ($m) => $m[1] . $m[2] . $this->mapSrcset($m[3], $mode) . $m[2],
            $html
        );

        // url(...) in style attributes / <style> blocks
        $html = preg_replace_callback(
            '/url\(\s*(["\']?)([^"\')]+)\1\s*\)/i',
            fn ($m) => 'url(' . $m[1] . $this->map(html_entity_decode($m[2], ENT_QUOTES), $mode, $m[2]) . $m[1] . ')',
            $html
        );

        // Remaining bare old-upload URLs in text / block comments.
        $hosts = $this->hostPattern();
        $html = preg_replace_callback(
            '#(?<![\w\\\\/.-])(?:(?:https?:)?//' . $hosts . ')?/wp-content/uploads/[^\s"\'<>()\\\\,]+#i',
            fn ($m) => $this->map($m[0], $mode),
            $html
        );

        // JSON-escaped variants inside Gutenberg comments: https:\/\/old.com\/wp-content\/uploads\/...
        $html = preg_replace_callback(
            '#(?:https?:)?\\\\/\\\\/' . $hosts . '\\\\/wp-content\\\\/uploads\\\\/[^\s"\'<>()]+?(?=["\s,}]|$)#i',
            function ($m) use ($mode) {
                $plain = str_replace('\\/', '/', $m[0]);
                $mapped = $this->map($plain, $mode);
                return $mapped === $plain ? $m[0] : str_replace('/', '\\/', $mapped);
            },
            $html
        );

        return $html;
    }

    private function mapSrcset(string $srcset, string $mode): string
    {
        $parts = array_map('trim', explode(',', $srcset));
        foreach ($parts as $i => $part) {
            if ($part === '') {
                continue;
            }
            $bits = preg_split('/\s+/', $part, 2);
            $bits[0] = $this->map(html_entity_decode($bits[0], ENT_QUOTES), $mode, $bits[0]);
            $parts[$i] = implode(' ', $bits);
        }
        return implode(', ', $parts);
    }

    /**
     * @param string      $ref      decoded reference
     * @param string|null $original the exact text found in the source (returned when unchanged)
     * @param bool        $escape   HTML-escape the result (false for plain single-value columns)
     */
    private function map(string $ref, string $mode, ?string $original = null, bool $escape = true): string
    {
        $original ??= $ref;
        if (!$this->resolver->isUploadReference($ref)) {
            $info = $this->resolver->inspect($ref);
            if ($info['status'] === ImageUrlResolver::UNMAPPABLE) {
                $this->seen[] = $info;
            }
            return $original;
        }

        $info = $this->resolver->inspect($ref);
        $this->seen[] = $info;

        if (!in_array($info['status'], self::REWRITABLE, true)) {
            return $original;
        }

        $new = $mode === self::MODE_PATH ? $info['path'] : $info['url'];
        if ($new === $ref) {
            return $original;
        }
        return $escape ? htmlspecialchars($new, ENT_QUOTES | ENT_HTML5, 'UTF-8', false) : $new;
    }

    private function hostPattern(): string
    {
        $hosts = $this->resolver->legacyHosts();
        if (!$hosts) {
            return '[a-z0-9.-]+'; // unknown legacy domain: any host
        }
        return '(?:' . implode('|', array_map(fn ($h) => preg_quote($h, '#'), $hosts)) . ')';
    }

    private function looksSerialized(string $v): bool
    {
        return (bool) preg_match('/^(?:a:\d+:\{|s:\d+:"|O:\d+:"|b:[01];|i:\d+;|d:[\d.E+-]+;|N;)/', $v);
    }
}
