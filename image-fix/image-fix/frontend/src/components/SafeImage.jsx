import { useEffect, useState } from 'react';
import { PLACEHOLDER_IMAGE, resolveImageUrl, resolveSrcSet } from '../lib/imageUrl';

const reported = new Set();

/** One console warning per broken URL, so real path problems stay visible. */
export function reportBrokenImage(url, context) {
  if (!url || reported.has(url)) return;
  reported.add(url);
  // eslint-disable-next-line no-console
  console.warn(
    `[image] failed to load ${url}` + (context ? ` (${context})` : '') +
      ' — check the Network tab: 404 = file missing or wrong path, 403 = permissions/.htaccess in public/uploads.'
  );
}

/**
 * Drop-in replacement for <img> that:
 *   - resolves legacy WordPress / relative paths via resolveImageUrl()
 *   - swaps to a placeholder if the image fails, without looping
 *   - logs each broken URL once (never hides the underlying problem)
 *
 *   <SafeImage src={post.featured_image} alt={post.title} className="card-img" />
 *   <SafeImage src={logo} fallback={null} />   // render nothing if broken
 */
export default function SafeImage({ src, srcSet, fallback = PLACEHOLDER_IMAGE, alt = '', onError, ...rest }) {
  const resolved = resolveImageUrl(src);
  const [failed, setFailed] = useState(false);

  // Reset when the source changes (lists, pagination, route changes).
  useEffect(() => setFailed(false), [resolved]);

  if (!resolved || failed) {
    if (!fallback) return null;
    return <img src={fallback} alt={alt} data-broken-src={resolved || undefined} {...rest} />;
  }

  return (
    <img
      src={resolved}
      srcSet={srcSet ? resolveSrcSet(srcSet) : undefined}
      alt={alt}
      loading={rest.loading ?? 'lazy'}
      {...rest}
      onError={(e) => {
        reportBrokenImage(resolved, src !== resolved ? `from ${src}` : undefined);
        setFailed(true);
        onError?.(e);
      }}
    />
  );
}
