// node --test tests/imageUrl.test.mjs
import test from 'node:test';
import assert from 'node:assert/strict';
import { resolveImageUrl, resolveSrcSet, bgImage, uploadRelativePath } from '../src/lib/imageUrl.js';

const opts = { baseUrl: 'http://localhost:8000', legacyHosts: ['example.com', 'www.example.com'] };
const r = (x) => resolveImageUrl(x, opts);

test('legacy and relative formats map to /uploads on the Laravel origin', () => {
  const want = 'http://localhost:8000/uploads/2024/05/image.jpg';
  for (const ref of [
    'https://example.com/wp-content/uploads/2024/05/image.jpg',
    'http://www.example.com/wp-content/uploads/2024/05/image.jpg',
    '//example.com/wp-content/uploads/2024/05/image.jpg',
    '/wp-content/uploads/2024/05/image.jpg',
    '../../wp-content/uploads/2024/05/image.jpg',
    '/uploads/2024/05/image.jpg',
    'uploads/2024/05/image.jpg',
    '2024/05/image.jpg',
  ]) assert.equal(r(ref), want, ref);
});

test('keeps query, encodes spaces once', () => {
  assert.equal(r('/wp-content/uploads/2024/05/a.jpg?ver=2'), 'http://localhost:8000/uploads/2024/05/a.jpg?ver=2');
  assert.equal(r('/uploads/2024/05/Team Photo.JPG'), 'http://localhost:8000/uploads/2024/05/Team%20Photo.JPG');
  assert.equal(r('/uploads/2024/05/Team%20Photo.JPG'), 'http://localhost:8000/uploads/2024/05/Team%20Photo.JPG');
});

test('leaves non-upload references alone', () => {
  assert.equal(r('/images/logo.svg'), '/images/logo.svg');
  assert.equal(r('https://cdn.other.com/wp-content/uploads/2024/05/a.jpg'), 'https://cdn.other.com/wp-content/uploads/2024/05/a.jpg');
  assert.equal(r('data:image/png;base64,AA'), 'data:image/png;base64,AA');
  assert.equal(r('http://localhost:8000/uploads/2024/05/a.jpg'), 'http://localhost:8000/uploads/2024/05/a.jpg');
  assert.equal(r(''), null);
  assert.equal(r(undefined), null);
  assert.equal(uploadRelativePath('/uploads/../.env'), null);
});

test('same-domain production build (empty base) yields root-relative', () => {
  assert.equal(resolveImageUrl('https://example.com/wp-content/uploads/2024/05/a.jpg', { baseUrl: '', legacyHosts: ['example.com'] }), '/uploads/2024/05/a.jpg');
});

test('srcset and background helpers', () => {
  assert.equal(
    resolveSrcSet('https://example.com/wp-content/uploads/2024/05/a-300x200.jpg 300w, /uploads/2024/05/a.jpg 1024w'),
    '/uploads/2024/05/a-300x200.jpg 300w, /uploads/2024/05/a.jpg 1024w'
  );
  assert.equal(bgImage('/wp-content/uploads/2024/05/a.jpg'), 'url("/uploads/2024/05/a.jpg")');
  assert.equal(bgImage(null), 'url("/images/placeholder.svg")');
});
