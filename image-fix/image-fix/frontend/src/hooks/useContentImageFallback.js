import { useEffect } from 'react';
import { PLACEHOLDER_IMAGE } from '../lib/imageUrl';
import { reportBrokenImage } from '../components/SafeImage';

/**
 * Placeholder + warning for <img> tags inside HTML rendered with
 * dangerouslySetInnerHTML (WordPress post content). URLs in that HTML are
 * already rewritten by the backend ImageHtml cast; this only catches files
 * that are genuinely missing.
 *
 *   const ref = useRef(null);
 *   useContentImageFallback(ref, post.content);
 *   return <div ref={ref} dangerouslySetInnerHTML={{ __html: post.content }} />;
 */
export default function useContentImageFallback(ref, html, placeholder = PLACEHOLDER_IMAGE) {
  useEffect(() => {
    const root = ref.current;
    if (!root) return undefined;

    const handle = (img) => {
      if (img.dataset.brokenSrc) return; // already swapped; don't loop on a broken placeholder
      reportBrokenImage(img.currentSrc || img.src, 'post content');
      img.dataset.brokenSrc = img.currentSrc || img.src;
      img.removeAttribute('srcset');
      if (placeholder) img.src = placeholder;
      else img.style.display = 'none';
    };

    const onError = (e) => {
      if (e.target instanceof HTMLImageElement) handle(e.target);
    };

    // error events don't bubble, so listen in the capture phase.
    root.addEventListener('error', onError, true);
    // Images that already failed before the listener was attached.
    root.querySelectorAll('img').forEach((img) => {
      if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) handle(img);
    });

    return () => root.removeEventListener('error', onError, true);
  }, [ref, html, placeholder]);
}
