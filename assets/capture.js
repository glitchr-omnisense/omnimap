/**
 * The buttons Omnimap\MapHtml::button() prints for whoever may change the
 * map: [data-omnimap-export] captures the live map (html2canvas, given by
 * the host: omnimap ships no copy) and posts its picture to the cache;
 * [data-omnimap-suppress] deletes the picture kept. The button turns green
 * when the server says OK, grey otherwise.
 *
 *   import { bindCapture } from './capture.js';
 *   bindCapture(document, { html2canvas: window.html2canvas, format: 'png', quality: 1 });
 *
 * Posted (application/x-www-form-urlencoded): image (a data: URL), tilesize,
 * _token; the answer is JSON with status "OK".
 */

const OK = '#9ce62a99';

function post(url, fields) {
  return fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
    body: new URLSearchParams(fields),
  }).then((response) => (response.ok ? response.json() : { status: 'BAD' }));
}

function canvasOf(html2canvas, element, options) {
  return new Promise((resolve, reject) => {
    // html2canvas 1.x returns a promise; 0.4 calls onrendered.
    const result = html2canvas(element, { useCORS: true, ...options, onrendered: resolve });
    if (result && typeof result.then === 'function') result.then(resolve, reject);
  });
}

function done(button, ok) {
  button.style.color = ok ? OK : 'grey';
}

export function bindCapture(root = document, options = {}) {
  const format = options.format || 'png';
  const quality = options.quality ?? 1;
  const tilesize = options.tilesize ?? 512;

  root.querySelectorAll('[data-omnimap-export]').forEach((button) => {
    if (button.dataset.omnimapBound === '1') return;
    button.dataset.omnimapBound = '1';
    button.addEventListener('click', () => {
      const html2canvas = options.html2canvas || window.html2canvas;
      const target = document.getElementById(button.dataset.omnimapTarget);
      if (!html2canvas || !target) return done(button, false);
      canvasOf(html2canvas, target, options.html2canvasOptions || {})
        .then((canvas) => post(button.dataset.omnimapExport, { image: canvas.toDataURL(`image/${format}`, quality), tilesize: String(tilesize), _token: button.dataset.omnimapToken || '' }))
        .then((answer) => done(button, answer.status === 'OK'), () => done(button, false));
    });
  });

  root.querySelectorAll('[data-omnimap-suppress]').forEach((button) => {
    if (button.dataset.omnimapBound === '1') return;
    button.dataset.omnimapBound = '1';
    button.addEventListener('click', () => {
      post(button.dataset.omnimapSuppress, { _token: button.dataset.omnimapToken || '' })
        .then((answer) => done(button, answer.status === 'OK'), () => done(button, false));
    });
  });
}
