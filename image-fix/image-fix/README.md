# Image URL fix — WordPress → Laravel + React

Drop-in fix for broken images after moving `wp-content/uploads` to Laravel's `public/uploads`.
One resolver decides how every image reference maps; the backend fixes stored data and API output,
the frontend only handles references that live in React code.

## Root cause

A broken image after this migration is almost always one of these. `php artisan media:audit` tells you which apply to your project and where:

| Cause | What it looks like |
|---|---|
| Stored content still points at `/wp-content/uploads/...` (old domain or root-relative). That path no longer exists. | Post HTML, featured-image columns, serialized options, Gutenberg block JSON |
| Relative paths resolve against the wrong origin. React dev server (`localhost:5173`) has no `/uploads`; `uploads/x.jpg` without a leading slash resolves to `/events/my-event/uploads/x.jpg` on nested routes. | Works on some pages, not others; works in prod, not dev |
| WordPress size variants (`image-300x200.jpg`) or `-scaled` originals were not copied, or the reference names the original while only `-scaled` exists. | `srcset` and thumbnails 404, full image works |
| Filename case differs (Windows/macOS host → Linux). | `Photo.JPG` on disk, `photo.jpg` in content |
| A WordPress/security-plugin `.htaccess` or restrictive permissions in `public/uploads`. | **403**, not 404, for files that do exist |
| URLs hardcoded or built with template literals in JSX/CSS. | Shows up in the `--code` part of the audit |
| Theme images (`/wp-content/themes/...`) referenced in content — that folder was never migrated. | Audit status `unmappable` |

A plain string replace of `/wp-content/uploads/` → `/uploads/` fixes the first case only, and silently rewrites references to files that don't exist. The resolver below checks the disk before changing anything.

## Files

New files (copy into your projects at the same paths):

```
backend/
  config/media.php
  app/Support/Media/ImageUrlResolver.php     core mapping + file checks (no Laravel dependency)
  app/Support/Media/ImageContentRewriter.php HTML / srcset / url() / JSON / serialized rewriting
  app/Support/Media/MediaScanner.php         code scan + uploads folder health check
  app/Providers/MediaServiceProvider.php
  app/Casts/ImageUrl.php                     cast for single-image columns
  app/Casts/ImageHtml.php                    cast for rich-content columns
  app/Console/Commands/MediaAudit.php        php artisan media:audit
  tests/standalone/run.php, e2e.php          framework-free tests
frontend/
  src/lib/imageUrl.js                        resolveImageUrl, resolveSrcSet, bgImage
  src/components/SafeImage.jsx               <img> with resolver + placeholder + warning
  src/hooks/useContentImageFallback.js       placeholder for images inside post HTML
  public/images/placeholder.svg
  tests/imageUrl.test.mjs
```

## Edits to your existing files

**1. Register the provider**

Laravel 11/12 — `bootstrap/providers.php`:
```php
return [
    App\Providers\AppServiceProvider::class,
    App\Providers\MediaServiceProvider::class,
];
```
Laravel 10 — add `App\Providers\MediaServiceProvider::class` to `providers` in `config/app.php`.

**2. Backend `.env`**
```dotenv
APP_URL=https://finnskerrisdaleladies.com
# Origin that serves /uploads. Empty = ASSET_URL, then APP_URL.
MEDIA_BASE_URL=
MEDIA_LEGACY_HOSTS=finnskerrisdaleladies.com,www.finnskerrisdaleladies.com
```
Local dev: `APP_URL=http://localhost:8000` (whatever `php artisan serve` uses).

**3. Tell the audit which columns hold images** — `config/media.php` → `audit.columns`, e.g.
```php
'posts' => ['primary_key' => 'id', 'columns' => ['content', 'excerpt', 'featured_image']],
'pages' => ['primary_key' => 'id', 'columns' => ['content', 'hero_image']],
```
Also set `audit.code_paths` to where your React `src` lives relative to the Laravel root.

**4. Fix the API layer once, on the models** (every model/column that holds images):
```php
use App\Casts\ImageHtml;
use App\Casts\ImageUrl;

class Post extends Model
{
    protected $casts = [
        'featured_image' => ImageUrl::class,   // one URL/path
        'content'        => ImageHtml::class,  // HTML, JSON, serialized
        'gallery'        => ImageHtml::class,  // JSON array of paths works too
    ];
}
```
API Resources and `->toArray()` now return working absolute URLs. Saving through the model stores root-relative `/uploads/...` so no domain is hardcoded. If a Resource builds an image URL by hand (`asset('wp-content/uploads/'.$x)`, `url(...)`, string concatenation), replace it with `app(\App\Support\Media\ImageUrlResolver::class)->toUrl($x)` — the audit's `--code=app` scan lists those lines.

**5. Keep old links working (optional, recommended)** — `routes/web.php`, *above* the SPA catch-all route:
```php
Route::get('wp-content/uploads/{path}', fn (string $path) => redirect('/uploads/' . $path, 301))
    ->where('path', '.*');
```
Requests for files that exist are served statically by Apache/Nginx before Laravel runs, so this only fires for old URLs (external sites, Google Images, cached emails).

**6. Frontend `.env`**
```dotenv
# Dev, React on :5173 and Laravel on :8000:
VITE_MEDIA_BASE_URL=http://localhost:8000
VITE_MEDIA_LEGACY_HOSTS=finnskerrisdaleladies.com,www.finnskerrisdaleladies.com
```
In production, leave `VITE_MEDIA_BASE_URL` empty if React and Laravel are served from the same domain; set it if Laravel is on a separate API host. CRA: use the `REACT_APP_` prefix instead of `VITE_`.

**7. React components** — use the lines the audit reports (`--code`):
```jsx
// before
<img src={`https://finnskerrisdaleladies.com/wp-content/uploads/${event.image}`} />
<div style={{ backgroundImage: `url(${post.featured_image})` }} />
<div dangerouslySetInnerHTML={{ __html: post.content }} />

// after
import SafeImage from '@/components/SafeImage';
import { bgImage } from '@/lib/imageUrl';
import useContentImageFallback from '@/hooks/useContentImageFallback';

<SafeImage src={event.image} alt={event.title} />
<div style={{ backgroundImage: bgImage(post.featured_image) }} />

const contentRef = useRef(null);
useContentImageFallback(contentRef, post.content);
<div ref={contentRef} dangerouslySetInnerHTML={{ __html: post.content }} />
```
Static CSS files: change `url(../wp-content/uploads/...)` to the `proposed` path the audit prints (CSS can't call a resolver).

## Normalise the stored data

```bash
php artisan media:audit                         # read-only: folder + DB + code
php artisan media:audit --report=storage/app/media-report.csv
# back up the database, then:
php artisan media:audit --db --write            # rewrites only refs whose file exists
php artisan media:audit --rollback=storage/logs/media-rewrite-YYYYMMDD-HHMMSS.json   # undo
```
The write is safe for serialized PHP (WordPress options/meta are re-serialized, so string lengths stay valid) and JSON. Running it twice changes nothing the second time. The casts stay on as a safety net for content edited later.

### Audit statuses

| Status | Meaning | Action |
|---|---|---|
| `ok` | Already `/uploads/...`, file exists | none |
| `mapped` | Old form, file exists at the new location | rewritten by `--write` / cast |
| `fallback` | Exact file missing; resized → original, `-scaled`, or case match used | rewritten; consider re-copying the variant |
| `missing` | No file on disk | **not rewritten**; copy the file from the old server into `public/uploads/<proposed path>` and re-run |
| `unreadable` | File exists, PHP can't read it | fix ownership/permissions |
| `unmappable` | Old-domain image outside `wp-content/uploads` (theme files) | copy into `public/images/` and edit by hand |
| `dynamic` | URL built at runtime in code | wrap with `resolveImageUrl()` |

Uploads folder checks flag `.htaccess` files (a common cause of 403s), PHP files, and files/folders that aren't world-readable.

## Testing

```bash
# backend logic (no framework needed)
php tests/standalone/run.php       # 40 resolver/rewriter cases
php tests/standalone/e2e.php       # code scan, SQLite rewrite + rollback, real HTTP fetch
# frontend logic
node --test tests/imageUrl.test.mjs
```
In the app:
1. `php artisan media:audit` — expect only `missing`/`unmappable` items you recognise.
2. `curl -sI "$APP_URL/uploads/2024/05/<real-file>.jpg"` → `200` with `Content-Type: image/jpeg`. A `403` means permissions or an `.htaccess` in `public/uploads`.
3. `curl -s "$APP_URL/api/posts/<id>" | grep -o '"[^"]*uploads[^"]*"'` — every URL should start with your base URL + `/uploads/`, none with `/wp-content/`.
4. Open the site with DevTools → Network → filter **Img**, sort by Status. Anything red is a genuinely missing file; the console shows one `[image] failed to load …` warning per URL and the placeholder is shown.
5. Old URL redirect: `curl -sI "$APP_URL/wp-content/uploads/2024/05/<real-file>.jpg"` → `301` to `/uploads/...`.
