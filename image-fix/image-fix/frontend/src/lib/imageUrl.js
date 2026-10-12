/**
 * Frontend image URL resolver.
 *
 * The backend (ImageUrl / ImageHtml casts) already returns working absolute
 * URLs for anything that comes from the API, and those pass through here
 * unchanged. This file exists for the references that live in the React code
 * itself: hardcoded paths, template-literal URLs, CSS backgrounds set in JSX.
 *
 * It mirrors the backend's mapping rules, but it cannot check whether a file
 * exists — that is done by `php artisan media:audit`. Missing files are
 * surfaced at runtime by <SafeImage> (placeholder + console warning).
 *
 * Env (Vite):
 *   VITE_MEDIA_BASE_URL   origin that serves /uploads (Laravel), e.g. http://localhost:8000
 *                         Leave empty in production when React and Laravel share a domain.
 *   VITE_MEDIA_LEGACY_HOSTS  comma list of the old WordPress hostnames
 *   VITE_MEDIA_UPLOADS_DIR   defaults to "uploads"
 *
 * CRA: rename to REACT_APP_* — readEnv() checks both.
 */

function readEnv(name) {
  let value;
  try {
    value = import.meta.env?.[`VITE_${name}`];
  } catch {
    /* not a Vite build */
  }
  if (value === undefined && typeof process !== 'undefined' && process.env) {
    value = process.env[`REACT_APP_${name}`];
  }
  return value ?? '';
}

export const MEDIA_BASE_URL = String(readEnv('MEDIA_BASE_URL')).replace(/\/+$/, '');
export const UPLOADS_DIR = String(readEnv('MEDIA_UPLOADS_DIR') || 'uploads').replace(/^\/+|\/+$/g, '');
export const LEGACY_HOSTS = String(readEnv('MEDIA_LEGACY_HOSTS'))
  .split(',')
  .map((h) => h.trim().toLowerCase())
  .filter(Boolean);

export const PLACEHOLDER_IMAGE = '/images/placeholder.svg';

const IMAGE_EXT = 'jpe?g|png|gif|webp|avif|svg|bmp|ico|tiff?|heic';
const BARE_WP_FILE = new RegExp(`^/?((?:19|20)\\d{2}/(?:0[1-9]|1[0-2])/[^/]+\\.(?:${IMAGE_EXT}))$`, 'i');

function splitSuffix(ref) {
  const i = ref.search(/[?#]/);
  return i === -1 ? [ref, ''] : [ref.slice(0, i), ref.slice(i)];
}

function parseHost(location) {
  const m = location.match(/^(?:[a-z][a-z0-9+.-]*:)?\/\/([^/?#]+)/i);
  return m ? m[1].toLowerCase().replace(/:\d+$/, '') : null;
}

function ownHost() {
  return MEDIA_BASE_URL ? parseHost(MEDIA_BASE_URL) : null;
}

/** Path below /uploads/ for anything that is an upload reference, else null. */
export function uploadRelativePath(ref, uploadsDir = UPLOADS_DIR) {
  if (!ref) return null;
  const [location] = splitSuffix(String(ref).trim());
  const path = location.replace(/^(?:[a-z][a-z0-9+.-]*:)?\/\/[^/]+/i, '').replace(/\\/g, '/');

  let rel = null;
  let m;
  if ((m = path.match(/(?:^|\/)wp-content\/uploads\/(.+)$/i))) rel = m[1];
  else if ((m = path.match(new RegExp(`^(?:\\./|/)?${uploadsDir}/(.+)$`)))) rel = m[1];
  else if ((m = path.match(BARE_WP_FILE))) rel = m[1];
  if (rel === null) return null;

  const parts = [];
  for (const seg of rel.split('/')) {
    if (!seg || seg === '.') continue;
    if (seg === '..') return null;
    parts.push(seg);
  }
  return parts.length ? parts.join('/') : null;
}

/**
 * Turn any image reference into a URL the browser can load.
 *
 *   resolveImageUrl('https://old.com/wp-content/uploads/2024/05/a.jpg') -> `${base}/uploads/2024/05/a.jpg`
 *   resolveImageUrl('/uploads/2024/05/a.jpg')                          -> `${base}/uploads/2024/05/a.jpg`
 *   resolveImageUrl('2024/05/a.jpg')                                   -> `${base}/uploads/2024/05/a.jpg`
 *   resolveImageUrl('/images/logo.svg')                                -> '/images/logo.svg' (app asset)
 *   resolveImageUrl('https://cdn.other.com/x.jpg')                     -> unchanged (external)
 *   resolveImageUrl(null)                                              -> null
 */
export function resolveImageUrl(ref, { baseUrl = MEDIA_BASE_URL, legacyHosts = LEGACY_HOSTS, uploadsDir = UPLOADS_DIR } = {}) {
  if (ref === null || ref === undefined) return null;
  const raw = String(ref).trim();
  if (!raw) return null;
  if (/^(data|blob):/i.test(raw)) return raw;

  const [location, suffix] = splitSuffix(raw);
  const host = parseHost(location);
  const base = String(baseUrl).replace(/\/+$/, '');
  const own = base ? parseHost(base) : ownHost();

  // Another site entirely (CDN, embed): leave it alone. When no legacy hosts
  // are configured, any host's /wp-content/uploads/ path is treated as legacy.
  if (host && host !== own && legacyHosts.length && !legacyHosts.includes(host)) return raw;
  if (host && host !== own && !legacyHosts.length && !/\/wp-content\/uploads\//i.test(location)) return raw;

  const rel = uploadRelativePath(location, uploadsDir);
  if (rel === null) return raw;

  // Keep already-encoded characters as they are; encode the rest per segment.
  const encoded = rel
    .split('/')
    .map((seg) => encodeURIComponent(safeDecode(seg)))
    .join('/');
  return `${base}/${uploadsDir}/${encoded}${suffix}`;
}

function safeDecode(s) {
  try {
    return decodeURIComponent(s);
  } catch {
    return s;
  }
}

/** Value for an inline style background: style={{ backgroundImage: bgImage(post.image) }} */
export function bgImage(ref, fallback = PLACEHOLDER_IMAGE) {
  const url = resolveImageUrl(ref) || fallback;
  return url ? `url("${url.replace(/["\\\n]/g, (c) => (c === '\n' ? '' : `\\${c}`))}")` : 'none';
}

/** Resolve every URL inside a srcset string. */
export function resolveSrcSet(srcset) {
  if (!srcset) return srcset;
  return srcset
    .split(',')
    .map((part) => {
      const [url, ...descriptor] = part.trim().split(/\s+/);
      return [resolveImageUrl(url), ...descriptor].join(' ');
    })
    .join(', ');
}
