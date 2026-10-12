<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * HTML helpers: allow-list sanitizer (XSS protection for rich-text content),
 * excerpt generation and first-image detection.
 */
class Html
{
    private const ALLOWED_TAGS = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'cite', 'code', 'dd', 'div', 'dl', 'dt', 'em',
        'figcaption', 'figure', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'iframe', 'img', 'li', 'ol',
        'p', 'pre', 's', 'small', 'span', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th',
        'thead', 'tr', 'u', 'ul', 'section', 'article', 'header', 'footer', 'picture', 'source', 'video',
    ];

    private const ALLOWED_ATTRS = [
        '*' => ['class', 'id', 'style', 'title', 'aria-label', 'aria-describedby', 'role', 'data-align', 'data-widget'],
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'srcset', 'sizes', 'alt', 'width', 'height', 'loading', 'decoding'],
        'iframe' => ['src', 'width', 'height', 'frameborder', 'allow', 'allowfullscreen', 'loading', 'referrerpolicy'],
        'source' => ['src', 'srcset', 'type', 'media', 'sizes'],
        'video' => ['src', 'controls', 'poster', 'width', 'height', 'muted', 'loop', 'playsinline'],
        'td' => ['colspan', 'rowspan'], 'th' => ['colspan', 'rowspan', 'scope'],
        'ol' => ['start', 'type'],
    ];

    private const IFRAME_HOSTS = ['www.google.com', 'maps.google.com', 'www.youtube.com', 'www.youtube-nocookie.com', 'player.vimeo.com'];

    public static function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $root = $doc->getElementById('__root');
        if (! $root) {
            return '';
        }
        self::cleanNode($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function cleanNode(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, ['script', 'style', 'object', 'embed', 'form', 'input', 'button', 'textarea', 'select', 'link', 'meta', 'base', 'svg', 'math'], true)) {
                    $node->removeChild($child);
                    continue;
                }
                if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                    // unwrap unknown element, keep its children
                    self::cleanNode($child);
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                    continue;
                }
                if ($tag === 'iframe' && ! self::allowedIframe($child->getAttribute('src'))) {
                    $node->removeChild($child);
                    continue;
                }
                $allowed = array_merge(self::ALLOWED_ATTRS['*'], self::ALLOWED_ATTRS[$tag] ?? []);
                foreach (iterator_to_array($child->attributes) as $attr) {
                    $name = strtolower($attr->name);
                    if (! in_array($name, $allowed, true)) {
                        $child->removeAttribute($attr->name);
                        continue;
                    }
                    if (in_array($name, ['href', 'src', 'poster'], true) && ! self::safeUrl($attr->value)) {
                        $child->removeAttribute($attr->name);
                    }
                    if ($name === 'style' && preg_match('/expression|javascript:|url\s*\(\s*[\'"]?\s*javascript/i', $attr->value)) {
                        $child->removeAttribute('style');
                    }
                }
                if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                    $child->setAttribute('rel', 'noopener noreferrer');
                }
                self::cleanNode($child);
            } elseif ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
            }
        }
    }

    private static function safeUrl(string $url): bool
    {
        $url = trim(html_entity_decode($url));
        if ($url === '' || str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        return (bool) preg_match('#^(https?:|mailto:|tel:)#i', $url);
    }

    private static function allowedIframe(string $src): bool
    {
        $host = parse_url($src, PHP_URL_HOST);

        return $host && in_array(strtolower($host), self::IFRAME_HOSTS, true);
    }

    public static function firstImage(string $html): ?string
    {
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $m)) {
            return html_entity_decode($m[1]);
        }

        return null;
    }

    public static function text(string $html): string
    {
        $text = preg_replace('/<(br|\/p|\/div|\/h[1-6]|\/li|\/figcaption)[^>]*>/i', ' ', $html);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    public static function excerpt(string $html, int $length = 160): string
    {
        $text = self::text($html);
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        $cut = mb_substr($text, 0, $length);
        $space = mb_strrpos($cut, ' ');

        return rtrim(mb_substr($cut, 0, $space ?: $length), " ,.;:-–").'…';
    }
}
